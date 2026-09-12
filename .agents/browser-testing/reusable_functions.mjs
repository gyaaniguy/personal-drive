// Shared action library. One function = one Playwright interaction + its checks,
// aggregated in a single `checkConditions` call (README §6). The test scripts
// import and call these; no action+check code lives inline in a script.

import { click, fill, goto, hover, reload } from "/home/aa/work/ai/playwright-testing/framework/global.mjs";
import { domCheck } from "/home/aa/work/ai/playwright-testing/framework/checker/domCheck.mjs";
import { networkCheck } from "/home/aa/work/ai/playwright-testing/framework/checker/networkCheck.mjs";
import { checkConditions } from "/home/aa/work/ai/playwright-testing/framework/helpers.mjs";
import { waitForDOM, expectLaravelError } from "/home/aa/work/ai/playwright-testing/framework/waitHelpers.mjs";
import { TestFiles } from "/home/aa/work/ai/playwright-testing/framework/utils/testFiles.mjs";
import { setLastActionTime } from "/home/aa/work/ai/playwright-testing/framework/checker/monitor.mjs";
import { mkdirSync, writeFileSync, readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

// ── Error logging ────────────────────────────────────────────────────────────
// On any failure, dump the full picture to .agents/browser-testing/logs/ so a red
// run leaves a durable, detailed record (steps reached, failing checks, stack) next
// to the scripts. Resolved from THIS module's location, so it is cwd-independent.
// Returns the file path written (for the console line), or null on write failure.
export function writeErrorLog(scriptName, results, error) {
    try {
        const dir = fileURLToPath(new URL("./logs/", import.meta.url));
        mkdirSync(dir, { recursive: true });
        const stamp = new Date().toISOString().replace(/[:.]/g, "-");
        const file = `${dir}${scriptName}-${stamp}.log`;
        const record = {
            script: scriptName,
            time: new Date().toISOString(),
            message: error?.message ?? String(error),
            // checkConditions attaches the failing checks (or browser errors) here.
            detail: error?.detail ?? null,
            results,
            stack: error?.stack ?? null,
        };
        writeFileSync(file, JSON.stringify(record, null, 2));
        return file;
    } catch (e) {
        console.error(`writeErrorLog failed: ${e.message}`);
        return null;
    }
}


// ── Login ──────────────────────────────────────────────────────────────────

// Login is THREE actions — fill, fill, submit — so it is three functions chained
// in the test script, never one `loginUser()`. A single function would report one
// failure for three possible causes and verify step N-2's action (README §6).

export async function fillUsername(page, { username }) {
    await waitForDOM(page, "#username", { timeout: 5000 });
    await fill(page, "#username", username);

    const filled = await domCheck(page)
        .inputValueIs("#username", username)
        .hasFocus("#username")
        .inputValueIs("#password", "") // the neighbouring field was not touched
        .elementCountIs("button:has-text('Log in')", 1)
        .matches();
    checkConditions(filled);
}

export async function fillPassword(page, { username, password }) {
    await fill(page, "#password", password);

    const filled = await domCheck(page)
        .inputValueIs("#password", password)
        .hasFocus("#password")
        .inputValueIs("#username", username) // this fill did not wipe the previous one
        .doesNotHaveFocus("#username")
        .matches();
    checkConditions(filled);
}

export async function submitLogin(page) {
    await click(page, "button:has-text('Log in')");

    const loginReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/login")
        .httpCodeIs(302) // form posts redirect — the controller returns 302, not 200
        .waitFor({ timeout: 15000 });
    await page.waitForURL(/\/drive/, { timeout: 15000 });
    await page.waitForLoadState("networkidle");

    const afterLogin = await domCheck(page)
        .pathnameIs("/drive")
        .elementDoesNotExist("#username")
        .elementDoesNotExist("#password")
        .elementCountIs('button[aria-label="New"]', 1)
        .matches();
    checkConditions(loginReq, afterLogin);
}

// ── New Menu ───────────────────────────────────────────────────────────────
// `button[aria-label="New"]` (UploadMenu.jsx) toggles a menu of five
// `button[role="menuitem"]` entries; `aria-expanded` mirrors the open state.

export async function openNewMenu(page) {
    await click(page, 'button[aria-label="New"]');

    const menuDom = await domCheck(page)
        .hasAttribute('button[aria-label="New"]', "aria-expanded", "true")
        .elementCountIs('button[role="menuitem"]:text-is("Create Folder")', 1)
        .elementCountIs('button[role="menuitem"]:text-is("Upload File")', 1)
        .matches();
    checkConditions(menuDom);
}

// ── Create Folder: Open Dialog ─────────────────────────────────────────────

export async function clickCreateFolderMenuItem(page) {
    await click(page, 'button[role="menuitem"]:text-is("Create Folder")');
    await waitForDOM(page, "#itemName", { timeout: 5000 });

    const dialogDom = await domCheck(page)
        .elementCountIs(".fixed.inset-0:has(h2)", 1)
        .elementCountIs("#itemName", 1)
        .isVisible("#itemName")
        .elementCountIs('button[type="submit"]:visible', 1)
        .matches();
    checkConditions(dialogDom);
}

// ── Create Folder: Fill Name ───────────────────────────────────────────────

export async function fillItemName(page, { itemName }) {
    await fill(page, "#itemName", itemName);

    const inputDom = await domCheck(page)
        .inputValueIs("#itemName", itemName)
        .hasFocus("#itemName")
        .elementCountIs('button[type="submit"]:visible', 1)
        .matches();
    checkConditions(inputDom);
}

// ── Create Folder: Submit ──────────────────────────────────────────────────
// The controller redirects, so the status is 302.

export async function submitCreateItem(page, { folderName, expectedHref = `/drive/${folderName}` }) {
    await click(page, 'button[type="submit"]:visible');

    const createReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/create-item")
        .httpCodeIs(302)
        .waitFor({ timeout: 15000 });

    // One-hop-late render, as with every Inertia POST in this app: the row is not
    // there yet when the 302 lands. Settle the follow-up request first (best-effort
    // waits, so the checks below report *which* expected row is missing instead of
    // dying on a timeout).
    await page.waitForLoadState("networkidle");
    await page.locator(`tr:has(span:text-is("${folderName}"))`).waitFor({ timeout: 5000 }).catch(() => {});

    const folderDom = await domCheck(page)
        .elementCountIs(`tr:has(span:text-is("${folderName}"))`, 1)
        .elementCountIs(`span.truncate:text-is("${folderName}")`, 1)
        .hasAttribute(`tr:has(span:text-is("${folderName}")) a`, "href", expectedHref)
        .elementDoesNotExist("#itemName")
        .elementExists('button[aria-label="New"]')
        .matches();
    checkConditions(createReq, folderDom);
}

// ── Navigate Into Folder ───────────────────────────────────────────────────

export async function navigateIntoFolder(page, { folderName, expectedItems = [] }) {
    await click(page, `tr:has(span:text-is("${folderName}")) a`);

    const navReq = await networkCheck(page)
        .methodIs("GET")
        .urlContains(`/drive/${folderName}`)
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.waitForURL(new RegExp(`/drive/${folderName}$`), { timeout: 15000 });
    await page.waitForLoadState("networkidle");

    let navDom = domCheck(page)
        .pathnameIs(`/drive/${folderName}`)
        .elementCountIs('nav[aria-label="Breadcrumb"] [aria-current="page"]', 1)
        .textIs('nav[aria-label="Breadcrumb"] [aria-current="page"]', folderName)
        // The breadcrumb above is what proves the click left the root: the root has no
        // `[aria-current="page"]` crumb at all (`gotoDriveRoot` asserts its absence).
        // Row count cannot stand in for that proof — an empty folder renders no table.
        .elementCountIs('button[aria-label="New"]', 1);
    for (const name of expectedItems) {
        navDom = navDom.elementCountIs(`tr:has(span.truncate:text-is("${name}"))`, 1);
    }
    const navDomResult = await navDom.matches();
    checkConditions(navReq, navDomResult);
}

// ── Upload Files ───────────────────────────────────────────────────────────
// One action: the menu item click that opens the chooser (call `openNewMenu`
// first) plus `setFiles`. Everything the upload produced is asserted here.

export async function uploadFiles(page, { filenames, contents = [] }) {
    const testFiles = new TestFiles();
    for (const [index, filename] of filenames.entries()) {
        // `contents` lets a caller assert on what it uploaded (the preview step reads
        // the body back); without it the placeholder convention still applies.
        testFiles.addText(filename, contents[index] ?? `content-${index}`);
    }

    const rowsBefore = await page.locator("tr span.truncate").count();

    try {
        const [fileChooser] = await Promise.all([
            page.waitForEvent("filechooser"),
            click(page, 'button[role="menuitem"]:text-is("Upload File")'),
        ]);
        await fileChooser.setFiles(testFiles.paths);

        // The upload's partial reload can still be in flight here; settle it
        // before this action's DOM checks and before the next action's scope.
        const uploadReq = await networkCheck(page)
            .methodIs("POST")
            .urlContains("/upload")
            .httpCodeIs(302)
            .waitFor({ timeout: 20000 });
        await page.waitForLoadState("networkidle");
        // The upload finishes with a "Files uploaded" flash, and that same response
        // re-renders the listing — so the rows can blink out and back in. Wait for the
        // flash (the settled end state), THEN for each row, so the checks below do not
        // run in the re-render gap. No swallow: a row that never arrives is a real
        // finding, not something to skip past.
        await page
            .locator('div[role="alert"]:has-text("uploaded")')
            .first()
            .waitFor({ timeout: 15000 })
            .catch(() => {});
        for (const filename of filenames) {
            await page
                .locator(`tr:has(span.truncate:text-is("${filename}"))`)
                .waitFor({ timeout: 15000 });
        }

        let uploadedDom = domCheck(page).elementCountIs(
            "tr span.truncate",
            rowsBefore + filenames.length
        );
        for (const filename of filenames) {
            uploadedDom = uploadedDom
                .elementCountIs(`span.truncate:text-is("${filename}")`, 1)
                .elementCountIs(`tr:has(span:text-is("${filename}"))`, 1);
        }
        const uploadDom = await uploadedDom
            .elementCountIs('button[aria-label="New"]', 1)
            .matches();
        checkConditions(uploadReq, uploadDom);
    } finally {
        // No checkConditions in here: a failing check above must keep its own
        // error output instead of being replaced by the temp-dir teardown.
        testFiles.cleanup();
    }
}

// ── Select File ────────────────────────────────────────────────────────────
// The row's checkbox is the first cell's input (`FileListRow.jsx`), and its
// `checked` state is driven by the selection the app holds — so the checkbox is
// the assertion that the selection registered, not a DOM detail beside it.

export async function selectFile(page, { filename }) {
    const rowSel = `tr:has(span:text-is("${filename}"))`;
    const checkboxSel = `${rowSel} input[type="checkbox"]`;
    // Baseline before the action: a selection that did not register leaves the
    // checked count unchanged, and this is what makes that fail.
    const checkedBefore = await page.locator('tr input[type="checkbox"]:checked').count();

    await click(page, checkboxSel);

    const selectDom = await domCheck(page)
        .elementCountIs(checkboxSel, 1)
        .isChecked(checkboxSel)
        .elementCountIs('tr input[type="checkbox"]:checked', checkedBefore + 1)
        // Scope control: the row itself was selected, not a neighbour.
        .elementCountIs(`${rowSel} input[type="checkbox"]:checked`, 1)
        // The bulk action bar renders only while something is selected
        // (`FileBrowserSection.jsx:365`); the per-row copies of these same buttons
        // carry `hidden`, so `:not(.hidden)` is the toolbar's own button and
        // `:visible` is what proves it is actionable.
        .elementCountIs('button[title="Delete selected files"]:not(.hidden):visible', 1)
        .elementCountIs('button[title="Share selected files"]:not(.hidden):visible', 1)
        .elementCountIs('button[title="Download selected files"]:not(.hidden):visible', 1)
        .matches();
    checkConditions(selectDom);
}

// ── Open Share Modal ───────────────────────────────────────────────────────

export async function openShareModal(page) {
    const dom = domCheck(page);

    await click(page, 'button[title="Share selected files"]:not(.hidden)');

    const modalDom = await dom
        .elementExists('#password')
        .elementExists('#expiry')
        .elementExists('#slug')
        .elementExists('button:has-text("Get Sharable Link")')
        .elementExists('.fixed.inset-0')
        .matches();
    checkConditions(modalDom);
}

// ── Fill Share Password ────────────────────────────────────────────────────

export async function fillSharePassword(page, { password }) {
    const dom = domCheck(page);

    await fill(page, "#password", password);

    const inputDom = await dom
        .inputValueIs("#password", password)
        .elementExists("#password")
        .elementExists("#slug")
        .elementExists('#expiry')
        .elementExists('button:has-text("Get Sharable Link")')
        .matches();
    checkConditions(inputDom);
}

// ── Get Share Link ─────────────────────────────────────────────────────────

export async function getShareLink(page) {
    const dom = domCheck(page);

    await click(page, 'button:has-text("Get Sharable Link")');

    const shareReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/share-files")
        .httpCodeIs(302) // Inertia router.post → RedirectResponse with the flashed link
        .waitFor({ timeout: 5000 });

    await page.locator("text=Share Link Generated").first().waitFor({ timeout: 5000 });

    const linkDom = await dom
        .htmlContains("Share Link Generated")
        .elementExists('input[readonly]')
        .hasAttribute('input[readonly]', 'value')
        // The submit button is `{!sharedLink && ...}`, so a generated link removes it.
        .elementDoesNotExist('button:has-text("Get Sharable Link")')
        .elementExists('#password')
        .matches();
    checkConditions(shareReq, linkDom);

    const shareLink = await page.evaluate(() => {
        const input = document.querySelector('input[readonly]');
        return input ? input.value : null;
    });

    return shareLink;
}

// ── Close Share Modal ──────────────────────────────────────────────────────

export async function closeShareModal(page) {
    const dom = domCheck(page);

    await page.evaluate(() => {
        const svgs = document.querySelectorAll('.fixed.inset-0 button svg');
        for (const svg of svgs) {
            const btn = svg.closest('button');
            if (btn) { btn.click(); return; }
        }
    });
    await page.waitForTimeout(300);

    const closedDom = await dom
        .elementDoesNotExist('.fixed.inset-0')
        .elementDoesNotExist('#password')
        .elementDoesNotExist('#slug')
        .elementExists('button[aria-label="New"]')
        .matches();
    checkConditions(closedDom);
}

// ── Guest: Enter Password ──────────────────────────────────────────────────

export async function guestEnterPassword(page, { password }) {
    const dom = domCheck(page);

    await waitForDOM(page, 'input[type="password"]', { timeout: 5000 });

    const wallDom = await dom
        .elementExists('h1:has-text("Enter Password For Share")')
        .elementExists('button[type="submit"]:has-text("Submit")')
        .elementExists('input[type="password"]')
        .matches();
    checkConditions(wallDom);

    await page.fill('input[type="password"]', password);

    const inputDom = await dom
        .inputValueIs('input[type="password"]', password)
        .elementExists('h1:has-text("Enter Password For Share")')
        .matches();
    checkConditions(inputDom);

    await click(page, 'button[type="submit"]:has-text("Submit")');

    const checkReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/shared-check-password")
        .waitFor({ timeout: 5000 });

    await page.waitForLoadState("networkidle");

    checkConditions(checkReq);
}

// ── Guest: Verify Files ───────────────────────────────────────────────────

export async function guestVerifyFiles(page, { filenames, absent = [] }) {
    const dom = domCheck(page);
    // On a fresh no-password load React may still be hydrating; wait for the first
    // expected row before asserting (a genuinely absent file still fails below — the
    // .catch only swallows the wait, not the check).
    if (filenames.length) {
        await page.locator(`span.truncate:has-text("${filenames[0]}")`).first().waitFor({ timeout: 10000 }).catch(() => {});
    }

    let checks = dom;
    for (const name of filenames) {
        checks = checks
            .htmlContains(name)
            .elementExists(`span.truncate:has-text("${name}")`);
    }
    // A file the owner removed from the share (moved out of a path share, or deleted)
    // must be gone here — absence is the delta a mutation against a shared file makes.
    for (const name of absent) {
        checks = checks.elementDoesNotExist(`span.truncate:text-is("${name}")`);
    }
    // Structure: the password wall is gone (proves the gate was passed) and the
    // owner-only share toolbar is absent for a guest.
    checks = checks
        .elementDoesNotExist('input[type="password"]')
        .elementDoesNotExist('h1:has-text("Enter Password For Share")')
        .elementDoesNotExist('button[title="Share selected files"]');

    const result = await checks.matches();
    checkConditions(result);
}

// ── Guest: Reload the already-authenticated share and re-verify ─────────────
// An authenticated guest keeps their tab open while the owner mutates the shared
// files. A reload re-fetches the share (GET /shared/{slug}, session still holds
// `shared_{slug}_authenticated`, so no password re-prompt — which also avoids the
// `throttle:shared` limiter on repeated /shared-check-password posts). The file
// list must then track the owner's rename/move/delete: `filenames` still visible,
// `absent` gone.
export async function reloadGuestShare(page, { filenames, absent = [] }) {
    await reload(page);

    const req = await networkCheck(page)
        .methodIs("GET")
        .urlContains("/shared/")
        .httpCodeIs(200)
        .waitFor({ timeout: 10000 });
    await page.waitForLoadState("networkidle");
    checkConditions(req);

    await guestVerifyFiles(page, { filenames, absent });
}

// ── Guest: Verify Password Wall ────────────────────────────────────────────

export async function guestVerifyPasswordWall(page) {
    const dom = domCheck(page);

    const result = await dom
        .elementExists('h1:has-text("Enter Password For Share")')
        .elementExists('input[type="password"]')
        .elementExists('button[type="submit"]:has-text("Submit")')
        .elementDoesNotExist('span.truncate')
        .elementDoesNotExist('button[aria-label="New"]')
        .elementDoesNotExist('.fixed.inset-0')
        .matches();
    checkConditions(result);
}

// ── Guest: Verify Upload Zone ──────────────────────────────────────────────

export async function guestVerifyUploadZone(page, { visible = true }) {
    const dom = domCheck(page);

    if (visible) {
        const result = await dom
            .elementExists('text=Guest Upload')
            .elementExists('button:has-text("Choose File to Upload")')
            .elementExists('text=Files cannot be overwritten once uploaded')
            .elementExists('text=Unlimited')
            .elementExists('input[type="file"]')
            .matches();
        checkConditions(result);
    } else {
        const result = await dom
            .elementDoesNotExist('text=Guest Upload')
            .elementDoesNotExist('button:has-text("Choose File to Upload")')
            .elementExists('button[aria-label="New"]')
            .elementExists('span.truncate')
            .matches();
        checkConditions(result);
    }
}

// ── Guest: Verify No Upload Buttons ────────────────────────────────────────

export async function guestVerifyNoUploadButtons(page) {
    const dom = domCheck(page);

    const result = await dom
        .elementDoesNotExist('text=Guest Upload')
        .elementDoesNotExist('button:has-text("Choose File to Upload")')
        .elementDoesNotExist('input[type="file"]')
        .elementDoesNotExist('button[title="Share selected files"]')
        .elementDoesNotExist('button[aria-label="Delete selected files"]')
        .elementExists('span.truncate')
        .matches();
    checkConditions(result);
}

// ── Guest: Upload File ─────────────────────────────────────────────────────

export async function guestUploadFile(page, { filename, content = "guest-content" }) {
    const dom = domCheck(page);
    const testFiles = new TestFiles();
    testFiles.addText(filename, content);

    const [fileChooser] = await Promise.all([
        page.waitForEvent("filechooser"),
        page.click('button:has-text("Choose File to Upload")'),
    ]);
    await fileChooser.setFiles(testFiles.paths);

    const uploadReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/shared-upload")
        .httpCodeIs(200)
        .waitFor({ timeout: 10000 });

    await page.waitForLoadState("networkidle");
    await page.locator(`text=${filename}`).first().waitFor({ timeout: 5000 });

    const uploadDom = await dom
        .htmlContains(filename)
        .elementExists(`span.truncate:has-text("${filename}")`)
        .elementExists('text=Guest Upload')
        .elementExists('button:has-text("Choose File to Upload")')
        .matches();
    checkConditions(uploadReq, uploadDom);

    testFiles.cleanup();
}

// ── Guest: Upload Folder ───────────────────────────────────────────────────

export async function guestUploadFolder(page, { folderName, filenames }) {
    const dom = domCheck(page);
    const testFiles = new TestFiles();
    for (const name of filenames) {
        testFiles.addText(`${folderName}/${name}`, `content-${name}`);
    }

    const [fileChooser] = await Promise.all([
        page.waitForEvent("filechooser"),
        page.click('button:has-text("Choose File to Upload")'),
    ]);
    await fileChooser.setFiles(testFiles.paths);

    const uploadReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/shared-upload")
        .waitFor({ timeout: 15000 });

    await page.waitForLoadState("networkidle");

    // Wait for at least one of the files to appear
    await page.locator(`text=${filenames[0]}`).first().waitFor({ timeout: 5000 });

    let checks = dom
        .elementExists('text=Guest Upload')
        .elementExists('button:has-text("Choose File to Upload")');
    for (const name of filenames) {
        checks = checks.htmlContains(name);
    }
    const folderDom = await checks.matches();
    checkConditions(uploadReq, folderDom);

    testFiles.cleanup();
}

// ── Guest: Select A Row (privilege probe) ──────────────────────────────────
// Selecting a row as a guest must expose ONLY the Download button in the toolbar.
// The admin bulk cluster (Delete / Share / Favorite / Cut) is each `isAdmin &&`
// gated (FileBrowserSection.jsx:390-414), so its ABSENCE with a LIVE selection is a
// real privilege negative check — an admin's identical selection renders them all.
// A hidden-but-present admin control that still POSTs would be the finding.
export async function guestSelectFile(page, { filename }) {
    const rowSel = `tr:has(span:text-is("${filename}"))`;
    const checkboxSel = `${rowSel} input[type="checkbox"]`;
    const dlToolbar = 'button[aria-label="Download selected files"]:not(.hidden):visible';
    const checkedBefore = await page.locator('tr input[type="checkbox"]:checked').count();

    await click(page, checkboxSel);

    const dom = await domCheck(page)
        .elementCountIs(checkboxSel, 1)
        .isChecked(checkboxSel)
        .elementCountIs('tr input[type="checkbox"]:checked', checkedBefore + 1)
        // Guest toolbar = Download only …
        .elementCountIs(dlToolbar, 1)
        // … and none of the owner-only bulk controls, even with something selected.
        .elementDoesNotExist('button[aria-label="Delete selected files"]')
        .elementDoesNotExist('button[aria-label="Share selected files"]')
        .elementDoesNotExist('button[aria-label="Add selected items to favorites"]')
        .elementDoesNotExist('button[aria-label="Add to favorites"]')
        .elementDoesNotExist('button[aria-label="New"]')
        .matches();
    checkConditions(dom);
}

// ── Guest: Download File (FIXED) ───────────────────────────────────────────
// Previously asserted a guest "Delete" button EXISTS — guests correctly have none,
// so the check could never pass (documented in aspects.md). Rewritten to verify a
// REAL guest download and to make admin-absence a NEGATIVE check:
//   • the toolbar Download control fires POST /download-files (DownloadButton.jsx:56,
//     axios blob) authorized by the share (DownloadController.php:63 guestVerified),
//   • the server returns the file (200; single file → the file's own basename,
//     DownloadService.php:18-19 / DownloadController.php:80-83),
//   • the browser actually receives a download (a JSON auth error fires none).
// Precondition: the row is already selected (guestSelectFile).
export async function guestDownloadFile(page, { filename }) {
    const rowSel = `tr:has(span:text-is("${filename}"))`;
    const checkboxSel = `${rowSel} input[type="checkbox"]`;
    const dlToolbar = 'button[aria-label="Download selected files"]:not(.hidden):visible';
    const checkedBefore = await page.locator('tr input[type="checkbox"]:checked').count();

    const [download] = await Promise.all([
        page.waitForEvent("download", { timeout: 20000 }),
        click(page, dlToolbar),
    ]);

    const downloadReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/download-files")
        .httpCodeIs(200)
        .waitFor({ timeout: 20000 });
    await page.waitForLoadState("networkidle");

    // The served name must be the selected file: a wrong-id download serves another.
    const servedName = download.suggestedFilename();
    if (servedName !== filename) {
        throw new Error(`guest download served "${servedName}", expected "${filename}"`);
    }

    const dom = await domCheck(page)
        // Download cleared the selection (DownloadButton.jsx:89): toolbar gone, nothing
        // checked — but the file is still listed (a download must not mutate the share).
        .elementCountIs(dlToolbar, 0)
        .elementCountIs('tr input[type="checkbox"]:checked', checkedBefore - 1)
        .isNotChecked(checkboxSel)
        .elementCountIs(`span.truncate:text-is("${filename}")`, 1)
        // Still no owner affordance reachable after the download.
        .elementDoesNotExist('button[aria-label="Delete selected files"]')
        .elementDoesNotExist('button[aria-label="Share selected files"]')
        .matches();
    checkConditions(downloadReq, dom);

    return { servedName };
}

// ── Rename: Hover File Row ─────────────────────────────────────────────────
// The per-row action cluster is `hidden group-hover:block` (`FileItem.jsx`), so
// the rename button sits in the DOM with display:none until the row is hovered.

export async function hoverFileRow(page, { filename }) {
    const rowSel = `tr:has(span:text-is("${filename}"))`;
    const renameBtnSel = `${rowSel} button:has(svg.lucide-text-cursor)`;

    // A baseline is only a baseline from off the rows: a previous helper can leave the
    // pointer parked on this row (e.g. `cancelDeleteSelected`), which would make the
    // cluster's "before" count already 1 and the delta below read as a failure.
    await page.mouse.move(0, 0);

    // Baseline before the action: the row's rename button is present but not
    // visible. Asserting the delta is what makes this check able to fail if the
    // hover-gated class changes.
    const visibleBefore = await page.locator(renameBtnSel + ":visible").count();

    await hover(page, rowSel);

    const hoverDom = await domCheck(page)
        .elementCountIs(rowSel, 1)
        .elementCountIs(`span.truncate:text-is("${filename}")`, 1)
        .elementCountIs(renameBtnSel, 1)
        // The delta, not the constant: 0 before the hover, 1 after it.
        .elementCountIs(renameBtnSel + ":visible", visibleBefore + 1)
        // Scope control: only the hovered row's cluster came into view.
        .elementCountIs("tr button:has(svg.lucide-text-cursor):visible", 1)
        .matches();
    checkConditions(hoverDom);
}

// ── Rename: Open Modal ─────────────────────────────────────────────────────

export async function openRenameModal(page, { filename }) {
    const dom = domCheck(page);
    const rowSel = `tr:has(span:text-is("${filename}"))`;
    const modalSel = '.fixed.inset-0:has(h2:text-is("Rename file"))';

    await click(page, `${rowSel} button:has(svg.lucide-text-cursor)`);

    // Render wait only — a timeout must not abort the run, or the checks below
    // could never report what is actually missing.
    await page.locator("#filename").waitFor({ timeout: 3000 }).catch(() => {});

    const modalDom = await dom
        .elementCountIs(modalSel, 1)
        .textIs(`${modalSel} h2`, "Rename file")
        .elementCountIs("#filename", 1)
        .inputValueIs("#filename", filename)
        .elementCountIs(`${modalSel} button[type="submit"]`, 1)
        .elementExists(`span.truncate:text-is("${filename}")`)
        .matches();
    checkConditions(modalDom);
}

// ── Rename: Fill New Name ──────────────────────────────────────────────────

export async function fillRenameInput(page, { filename, unchanged = [] }) {
    const modalSel = '.fixed.inset-0:has(h2:text-is("Rename file"))';

    await fill(page, "#filename", filename);

    // The list behind the modal is the neighbour: typing must not disturb it.
    let checks = domCheck(page)
        .inputValueIs("#filename", filename)
        .elementCountIs(modalSel, 1)
        .elementCountIs("#filename", 1)
        .elementCountIs(`${modalSel} button[type="submit"]`, 1);
    for (const name of unchanged) {
        checks = checks.elementCountIs(`span.truncate:text-is("${name}")`, 1);
    }
    const inputDom = await checks.matches();
    checkConditions(inputDom);
}

// ── Rename: Submit ─────────────────────────────────────────────────────────
// `FileRenameController::index` returns `redirect()->back()` — a 302, not 200.

export async function submitRename(page, { oldFilename, newFilename, unchanged = [], folderName, expectedPath = `/drive/${folderName}`, fileCount }) {
    const modalSel = '.fixed.inset-0:has(h2:text-is("Rename file"))';

    await click(page, `${modalSel} button[type="submit"]`);

    const renameReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/rename-file")
        .httpCodeIs(302)
        .waitFor({ timeout: 15000 });

    // The 302 is only the server's half: Inertia follows the redirect with a GET
    // and applies it a moment later, so `networkidle` can resolve in that gap and
    // the checks would read the pre-rename page. Wait for the outcome to render;
    // if it never does, the checks below fail.
    await page.waitForLoadState("networkidle");
    await page.locator(`span.truncate:text-is("${newFilename}")`).waitFor({ timeout: 5000 }).catch(() => {});
    await page.locator(`div[role="alert"]:has-text("Renamed to ${newFilename}")`).waitFor({ timeout: 5000 }).catch(() => {});

    // The row total is scope control: a rename that adds the new row without
    // removing the old one leaves one row too many, and a rename applied to the
    // wrong file leaves the counts right but the names wrong.
    let checks = domCheck(page)
        .elementCountIs("tr span.truncate", fileCount)
        .elementCountIs(`span.truncate:text-is("${newFilename}")`, 1)
        .elementDoesNotExist(`span.truncate:text-is("${oldFilename}")`)
        .elementDoesNotExist(modalSel)
        .elementCountIs('div[role="alert"]', 1)
        .hasClass('div[role="alert"]', "bg-success")
        .elementContains('div[role="alert"]', `Renamed to ${newFilename}`)
        .pathnameIs(expectedPath)
        .elementCountIs('button[aria-label="New"]', 1);
    for (const name of unchanged) {
        checks = checks.elementCountIs(`span.truncate:text-is("${name}")`, 1);
    }
    const renamedDom = await checks.matches();
    checkConditions(renameReq, renamedDom);
}

// ── Rename: Reload And Re-read ─────────────────────────────────────────────

export async function reloadDrivePage(page, { folderName, expectedPath = `/drive/${folderName}`, present = [], absent = [] }) {
    await reload(page);

    const reloadReq = await networkCheck(page)
        .methodIs("GET")
        .urlContains(expectedPath)
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");

    let checks = domCheck(page)
        .pathnameIs(expectedPath)
        .elementCountIs('nav[aria-label="Breadcrumb"] [aria-current="page"]', 1)
        .textIs('nav[aria-label="Breadcrumb"] [aria-current="page"]', folderName)
        // A real round-trip: the flash from the previous action is not re-rendered
        // by the server, so a stale success banner means nothing was re-fetched.
        .elementCountIs('div[role="alert"]', 0)
        .elementCountIs('button[aria-label="New"]', 1);
    for (const name of present) {
        checks = checks.elementCountIs(`span.truncate:text-is("${name}")`, 1);
    }
    for (const name of absent) {
        checks = checks.elementDoesNotExist(`span.truncate:text-is("${name}")`);
    }
    const reloadDom = await checks.matches();
    checkConditions(reloadReq, reloadDom);
}

// ── Navigate To The Drive Root ─────────────────────────────────────────────

export async function gotoDriveRoot(page, { driveUrl }) {
    await goto(page, driveUrl);

    const rootReq = await networkCheck(page)
        .methodIs("GET")
        .urlContains("/drive")
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");

    const rootDom = await domCheck(page)
        .pathnameIs("/drive")
        // The breadcrumb only renders when a path is set, so its absence is what
        // proves this is the root and not a folder one level down.
        .elementCountIs('nav[aria-label="Breadcrumb"]', 0)
        .elementCountIs('button[aria-label="New"]', 1)
        .matches();
    checkConditions(rootReq, rootDom);
}

// ── Navigate To Root Via The Breadcrumb (Inertia, state-preserving) ────────
// Clicking the breadcrumb Home link is an Inertia visit, so app-level React
// state (the CutFilesProvider clipboard) survives — unlike gotoDriveRoot's full
// page.goto, which resets it. Use this when a cut is in flight.
export async function navigateToRootViaBreadcrumb(page) {
    await click(page, 'nav[aria-label="Breadcrumb"] a[href="/drive"]');

    const rootReq = await networkCheck(page)
        .methodIs("GET")
        .urlContains("/drive")
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.waitForURL(/\/drive$/, { timeout: 15000 });
    await page.waitForLoadState("networkidle");

    const rootDom = await domCheck(page)
        .pathnameIs("/drive")
        .elementCountIs('nav[aria-label="Breadcrumb"]', 0)
        .elementCountIs('button[aria-label="New"]', 1)
        .matches();
    checkConditions(rootReq, rootDom);
}

// ── Delete Row Item ────────────────────────────────────────────────────────
// Uses the per-row delete control (same hover cluster as the rename button), so
// it always targets the named row's own id and needs no multi-select toolbar.

export async function deleteRowItem(page, { filename, expectedPath = "/drive" }) {
    const dom = domCheck(page);
    const deleteBtnSel = `tr:has(span:text-is("${filename}")) button[aria-label="Delete selected files"]:visible`;
    page.once("dialog", (dialog) => dialog.accept());
    await click(page, deleteBtnSel);

    const deleteReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/delete-files")
        .httpCodeIs(302)
        .waitFor({ timeout: 15000 });

    // Same one-hop-late client update as the rename: wait for the row to be gone
    // before asserting its absence, so a slow response is not read as a failure.
    await page.waitForLoadState("networkidle");
    await page
        .waitForFunction(
            (name) => {
                const spans = [...document.querySelectorAll("span.truncate")];
                return spans.every((span) => span.textContent.trim() !== name);
            },
            filename,
            { timeout: 5000 }
        )
        .catch(() => {});

    const deletedDom = await dom
        .elementDoesNotExist(`span.truncate:text-is("${filename}")`)
        .elementDoesNotExist(`a:has-text("${filename}")`)
        .pathnameIs(expectedPath)
        .elementExists('button[aria-label="New"]')
        .matches();
    checkConditions(deleteReq, deletedDom);
}

// ── Seed The Drive View State (ListView) ───────────────────────────────────
// The drive list renders from localStorage (`FileBrowserSection.jsx:216/227`): a
// leftover `viewMode` from an earlier run changes the markup every row selector here
// matches. Seeded before the first row is touched; the reload is the action.

export async function seedDriveViewState(page) {
    await page.evaluate(() => {
        localStorage.setItem("viewMode", "ListView");
        localStorage.removeItem("sortDetails");
    });
    await reload(page);

    const seedReq = await networkCheck(page)
        .methodIs("GET")
        .urlContains("/drive")
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");

    const seedDom = await domCheck(page)
        .pathnameIs("/drive")
        // The seeded value drove the UI: the List view button carries the active class
        // and the Tile view button does not (`FileBrowserSection.jsx:430`).
        .hasClass('button[aria-label="List view"]', "bg-gray-900")
        .doesNotHaveClass('button[aria-label="Tile view"]', "bg-gray-900")
        .elementCountIs("#searchbox", 1)
        .elementCountIs('button[aria-label="New"]', 1)
        .matches();
    checkConditions(seedReq, seedDom);
}

// ── Search: Fill The Box ───────────────────────────────────────────────────

export async function fillSearchBox(page, { query }) {
    await fill(page, "#searchbox", query);

    const filled = await domCheck(page)
        .inputValueIs("#searchbox", query)
        .hasFocus("#searchbox")
        .elementCountIs('form:has(#searchbox) button:has-text("Search")', 1)
        // The clear "✕" only renders once the box has a value (`SearchBar.jsx:20`).
        .elementCountIs('form:has(#searchbox) button[type="button"]', 1)
        .matches();
    checkConditions(filled);
}

// ── Search: Submit ─────────────────────────────────────────────────────────
// The search is an Inertia POST that renders in place — 200, not the 302 the
// form-POST endpoints return — and the browser URL becomes `/search-files`.

export async function clickSearch(page, { query, expectPresent = [], expectAbsent = [] }) {
    await click(page, 'form:has(#searchbox) button:has-text("Search")');

    const searchReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/search-files")
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");
    // The list is a client render after the response; best-effort so a missing row is
    // reported by the checks below instead of aborting on a timeout.
    await page.locator("tr span.truncate").first().waitFor({ timeout: 5000 }).catch(() => {});

    let dom = domCheck(page)
        .pathnameIs("/search-files")
        .inputValueIs("#searchbox", query)
        // Search mode does not render the upload browser at all
        // (`FileBrowserSection.jsx:420`), which is what proves the result page loaded.
        .elementCountIs('button[aria-label="New"]', 0)
        // Scope control: exactly the matches asked for — a search that returned
        // everything, or one result too many, fails here.
        .elementCountIs("tr span.truncate", expectPresent.length);
    for (const label of expectPresent) {
        dom = dom.elementCountIs(`tr:has(span.truncate:text-is("${label}"))`, 1);
    }
    for (const label of expectAbsent) {
        dom = dom.elementCountIs(`tr:has(span.truncate:text-is("${label}"))`, 0);
    }
    if (expectPresent.length === 0) {
        // No matches renders the empty state instead of a table
        // (`FileBrowserSection.jsx:522`).
        dom = dom.elementCountIs('button:has-text("Go Back")', 1).htmlContains("This folder does not exist");
    }
    const searchDom = await dom.matches();
    checkConditions(searchReq, searchDom);
}

// ── Open A File Preview ────────────────────────────────────────────────────
// `handleFileClick` opens the viewer for previewable types; a `.txt` is
// `file_type === "text"`, which is what a text fixture would not be if the upload
// renamed it. The body is fetched from `/fetch-file/{id}` after the modal mounts.

export async function openFilePreview(page, { label, content }) {
    await click(page, `tr:has(span.truncate:text-is("${label}")) div[data-file-id]`);

    await page
        .locator(`.fixed.inset-0 pre:has-text("${content}")`)
        .waitFor({ timeout: 8000 })
        .catch(() => {});

    const previewDom = await domCheck(page)
        .elementCountIs(".fixed.inset-0", 1)
        .elementCountIs(".fixed.inset-0 pre", 1)
        // The right file's body, not just any viewer: this fails if the row opened a
        // different file (or the sibling).
        .elementContains(".fixed.inset-0 pre", content)
        // Opening a preview is not a navigation — the result list is still underneath.
        .pathnameIs("/search-files")
        .elementCountIs(`tr:has(span.truncate:text-is("${label}"))`, 1)
        .matches();
    checkConditions(previewDom);
}

// ── Close The Preview (Escape) ─────────────────────────────────────────────
// The viewer's modal is overlay-click-closable, but the dialog is centred under the
// overlay, so a computed click can land inside it. Escape is the viewer's own binding
// (`MediaViewer.jsx:81`). No wrapper covers a key press, so the scope is opened by
// hand (README §5).

export async function closeFilePreview(page, { label }) {
    setLastActionTime(page);
    await page.keyboard.press("Escape");
    await page.waitForTimeout(500);

    const closedDom = await domCheck(page)
        .elementCountIs(".fixed.inset-0", 0)
        .elementCountIs("pre", 0)
        // Closing is not a navigation and does not re-run the search: the same single
        // result row is still listed.
        .pathnameIs("/search-files")
        .elementCountIs('button[aria-label="New"]', 0)
        .elementCountIs(`tr:has(span.truncate:text-is("${label}"))`, 1)
        .matches();
    checkConditions(closedDom);
}

// ── Download The Selected Row ──────────────────────────────────────────────
// Select first (`selectFile`), then this: the toolbar's own download button, told
// apart from the per-row copy by `:not(.hidden)` — the row copies carry `hidden`.

export async function downloadSelectedRow(page, { label, expectedFilename, expectedPath = "/search-files" }) {
    const checkboxSel = `tr:has(span.truncate:text-is("${label}")) input[type="checkbox"]`;
    const toolbarBtn = 'button[aria-label="Download selected files"]:not(.hidden):visible';
    const checkedBefore = await page.locator('tr input[type="checkbox"]:checked').count();

    const [download] = await Promise.all([
        page.waitForEvent("download", { timeout: 20000 }),
        click(page, toolbarBtn),
    ]);

    const downloadReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/download-files")
        .httpCodeIs(200)
        .waitFor({ timeout: 20000 });
    await page.waitForLoadState("networkidle");
    await page.locator(toolbarBtn).waitFor({ state: "detached", timeout: 5000 }).catch(() => {});

    // The file served must be the selected one. A filename check is the only place
    // this can be observed, and it fails when the request carried the wrong ids.
    const servedName = download.suggestedFilename();
    if (servedName !== expectedFilename) {
        throw new Error(`download served "${servedName}", expected "${expectedFilename}"`);
    }

    const downloadDom = await domCheck(page)
        // The selection was cleared by the download (`DownloadButton.jsx:90`): the
        // toolbar is gone and nothing is left checked.
        .elementCountIs(toolbarBtn, 0)
        .elementCountIs('tr input[type="checkbox"]:checked', checkedBefore - 1)
        .elementCountIs(checkboxSel, 1)
        .isNotChecked(checkboxSel)
        .pathnameIs(expectedPath)
        .matches();
    checkConditions(downloadReq, downloadDom);

    return { servedName };
}

// ── Delete: Cancel The Confirm (row control) ────────────────────────────────
// Delete confirms first (`DeleteButton.jsx:12`). Dismissing it must leave the file
// alone — including no request at all — which is what a handler that deletes
// regardless of the answer would break.
//
// This drives the *row's own* delete button on purpose: that copy sits inside the
// item, whose `onClick` opens it, so a dismissed click used to bubble up and open the
// file behind the dialog (observed 2026-09-10, fixed by moving `stopPropagation`
// ahead of the confirm). The `.fixed.inset-0` check below is its regression guard.

export async function cancelDeleteSelected(page, { filename, siblingName }) {
    const rowSel = `tr:has(span:text-is("${filename}"))`;
    const rowDeleteBtn = `${rowSel} button[aria-label="Delete selected files"]:visible`;

    // The cluster is `hidden group-hover:block`, and the pointer must still be over the
    // row when the click lands — so hover immediately before, with nothing between.
    await hover(page, rowSel);

    let dialogMessage = null;
    page.once("dialog", (dialog) => {
        dialogMessage = dialog.message();
        dialog.dismiss();
    });
    await click(page, rowDeleteBtn);
    // Give a request that must not happen the time to happen: the negative network
    // check below can only catch it if it has already been logged.
    await page.waitForTimeout(2000);

    if (dialogMessage !== "Confirm Deletion?") {
        throw new Error(
            `delete raised ${JSON.stringify(dialogMessage)}, expected the "Confirm Deletion?" confirm`
        );
    }

    const noDeleteReq = await networkCheck(page).urlDoesNotContain("/delete-files").matches();
    const cancelDom = await domCheck(page)
        .elementCountIs(`span.truncate:text-is("${filename}")`, 1)
        .elementCountIs(`tr:has(span.truncate:text-is("${filename}"))`, 1)
        .elementCountIs(`span.truncate:text-is("${siblingName}")`, 1)
        // No "Deleted …" banner, and no viewer opened behind the dismissed dialog.
        .elementCountIs('div[role="alert"]', 0)
        .elementCountIs(".fixed.inset-0", 0)
        .matches();
    checkConditions(noDeleteReq, cancelDom);
}

// ── Reload The Search Page ─────────────────────────────────────────────────
// `GET /search-files` is a redirect route (`routes/web.php:59`), so a reload cannot
// re-run the POST: the browser lands on the drive root with an empty search box.

export async function reloadSearchPage(page) {
    await reload(page);

    const searchReloadReq = await networkCheck(page)
        .methodIs("GET")
        .urlContains("/search-files")
        .httpCodeIs(302)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");

    const reloadDom = await domCheck(page)
        .pathnameIs("/drive")
        .inputValueIs("#searchbox", "")
        .elementCountIs('button[aria-label="New"]', 1)
        .matches();
    checkConditions(searchReloadReq, reloadDom);
}

// ── Deep Link To A Deleted File ────────────────────────────────────────────
// After the delete the path must stop serving the file: no viewer, no row, and the app
// reports the path as missing rather than rendering content for a row the database no
// longer has.

export async function verifyFileUnreachable(page, { url, path }) {
    await goto(page, url);

    const deepLinkReq = await networkCheck(page)
        .methodIs("GET")
        .urlContains(path)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");

    const unreachableDom = await domCheck(page)
        .pathnameIs(path)
        .elementCountIs("pre", 0)
        .elementCountIs("tr span.truncate", 0)
        .htmlContains("This folder does not exist")
        .elementCountIs('button[aria-label="New"]', 1)
        .matches();
    checkConditions(deepLinkReq, unreachableDom);
}

// ── Favorites: Add via the row star ────────────────────────────────────────
// The star is `hidden group-hover:flex` (FavoriteButton.jsx), so the row must be
// hovered first (call hoverFileRow). Clicking it POSTs /favorites (axios, JSON
// 200) and flips the button's aria-label from "Add to favorites" to
// "Already a favorite" and adds the active `bg-blue-700` class.
export async function addFavorite(page, { filename }) {
    const rowSel = `tr:has(span:text-is("${filename}"))`;
    const addSel = `${rowSel} button[aria-label="Add to favorites"]`;
    const favSel = `${rowSel} button[aria-label="Already a favorite"]`;

    // The star is hover-revealed (`hidden group-hover:flex`). The upload flash can
    // dismiss between hoverFileRow and here, shifting the layout so the parked
    // pointer drifts off the row; re-park it on the row immediately before the
    // click so the click needs no scroll that would drop the hover and hide the star.
    await hover(page, rowSel);
    await click(page, addSel);

    const favReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/favorites")
        .httpCodeIs(200)
        .waitFor({ timeout: 10000 });
    await page.waitForLoadState("networkidle");

    const favDom = await domCheck(page)
        // 0 → 1: the row's star flipped to the favorited label, and the
        // unfavorited label for this row is gone.
        .elementCountIs(favSel, 1)
        .elementDoesNotExist(addSel)
        .hasClass(favSel, "bg-blue-700")
        .matches();
    checkConditions(favReq, favDom);
}

// ── Favorites: Remove via the row star ─────────────────────────────────────
// Clicking a favorited row star DELETEs /favorites/{id} (JSON 200) and flips the
// label back. Hover the row first.
export async function removeFavorite(page, { filename }) {
    const rowSel = `tr:has(span:text-is("${filename}"))`;
    const addSel = `${rowSel} button[aria-label="Add to favorites"]`;
    const favSel = `${rowSel} button[aria-label="Already a favorite"]`;

    await hover(page, rowSel);
    await click(page, favSel);

    const unfavReq = await networkCheck(page)
        .methodIs("DELETE")
        .urlContains("/favorites/")
        .httpCodeIs(200)
        .waitFor({ timeout: 10000 });
    await page.waitForLoadState("networkidle");

    const unfavDom = await domCheck(page)
        .elementCountIs(addSel, 1)
        .elementDoesNotExist(favSel)
        .matches();
    checkConditions(unfavReq, unfavDom);
}

// ── Favorites: Open the menu and verify its contents ───────────────────────
// The header star toggles #favorites-list (FavoritesMenu.jsx). Each entry has a
// remove button keyed on the filename, which is the precise per-file anchor.
export async function openFavoritesMenu(page, { present = [], absent = [] }) {
    await click(page, 'button[aria-label="Favorites"]');

    let checks = domCheck(page)
        .elementExists("#favorites-list")
        .elementExists('button[aria-label="Favorites"]');
    for (const name of present) {
        checks = checks.elementCountIs(`#favorites-list button[aria-label="Remove ${name} from favorites"]`, 1);
    }
    for (const name of absent) {
        checks = checks.elementDoesNotExist(`#favorites-list button[aria-label="Remove ${name} from favorites"]`);
    }
    const menuDom = await checks.matches();
    checkConditions(menuDom);
}

// ── Favorites: Close the menu ──────────────────────────────────────────────
// Toggling the header star again removes #favorites-list from the DOM.
export async function closeFavoritesMenu(page) {
    await click(page, 'button[aria-label="Favorites"]');

    const closedDom = await domCheck(page)
        .elementDoesNotExist("#favorites-list")
        .elementExists('button[aria-label="Favorites"]')
        .matches();
    checkConditions(closedDom);
}

// ── Favorites: bulk toggle the current multi-selection (list view; aspect D) ──
// The shared toolbar star (FileBrowserSection.jsx:393, aria-label "Add selected
// items to favorites") fires handleAddFavorites → toggleFavorites(Array.from(
// selectedFiles), true) (FileBrowserSection.jsx:106-161). The branch is decided by
// whether EVERY selected id is already favorited:
//   • mode "add"    — not all favorited → ONE POST /favorites (axios JSON 200) with
//                     only the not-yet-favorited ids; alert "Added to favorites".
//   • mode "remove" — all favorited → Promise.all of DELETE /favorites/{favoriteId}
//                     (each 200); alert "Removed from favorites".
// Either branch sets clearSelection=true, so the set empties, the whole bulk toolbar
// is withdrawn, and nothing stays checked. WHICH files changed is verified through
// the favorites menu by the caller (scope + persistence). The success-alert text is
// the tell for which branch actually ran: a selection whose favorited-state the app
// tracked wrong would take the other branch and flip this text → RED.
export async function bulkFavoriteSelected(page, { mode }) {
    const FAV_TOOLBAR = 'button[aria-label="Add selected items to favorites"]:visible';
    const DELETE_TOOLBAR = 'button[aria-label="Delete selected files"]:not(.hidden):visible';
    const checkedSel = 'tbody input[type="checkbox"]:checked';
    const isAdd = mode === "add";

    await click(page, FAV_TOOLBAR);

    const favReq = await networkCheck(page)
        .methodIs(isAdd ? "POST" : "DELETE")
        .urlContains(isAdd ? "/favorites" : "/favorites/")
        .httpCodeIs(200)
        .waitFor({ timeout: 10000 });
    await page.waitForLoadState("networkidle");

    const dom = await domCheck(page)
        .elementCountIs(checkedSel, 0)
        .elementCountIs(FAV_TOOLBAR, 0)
        .elementCountIs(DELETE_TOOLBAR, 0)
        .elementContains('div[role="alert"]', isAdd ? "Added to favorites" : "Removed from favorites")
        .hasClass('div[role="alert"]', "bg-success")
        .matches();
    checkConditions(favReq, dom);
}

// ── Favorites: assert per-row star state (verification only; desync hunt) ────
// The row FavoriteButton (FavoriteButton.jsx) labels itself "Already a favorite"
// when the file's id is in favoriteFileIds, else "Add to favorites" — so its label
// is the row's own view of the favorite state, independent of the favorites menu.
// The star lives in a `hidden group-hover:flex` cluster, but the element is present
// in the DOM regardless of hover, so a count read needs no hover. Reading BOTH the
// menu (openFavoritesMenu) and the row star after a reload/move catches a desync
// where the two disagree — a menu-only check would miss a stale row star. Not an
// interaction: a pure DOM assertion.
export async function assertRowFavoriteState(page, { favorited = [], notFavorited = [] }) {
    let dom = domCheck(page);
    for (const name of favorited) {
        const row = `tr:has(span:text-is("${name}"))`;
        dom = dom
            .elementCountIs(`${row} button[aria-label="Already a favorite"]`, 1)
            .elementDoesNotExist(`${row} button[aria-label="Add to favorites"]`);
    }
    for (const name of notFavorited) {
        const row = `tr:has(span:text-is("${name}"))`;
        dom = dom
            .elementCountIs(`${row} button[aria-label="Add to favorites"]`, 1)
            .elementDoesNotExist(`${row} button[aria-label="Already a favorite"]`);
    }
    checkConditions(await dom.matches());
}

// ── Move: Cut the current selection ────────────────────────────────────────
// Precondition: a file is selected (selectFile). The toolbar Cut button
// (CutButton.jsx) clears the selection into the cut buffer; the Paste affordance
// then appears (PasteButton renders while cutFiles.size > 0).
export async function cutSelected(page, { filename }) {
    await click(page, 'button[aria-label="Cut selected files"]:not(.hidden):visible');
    await page.waitForLoadState("networkidle");

    const cutDom = await domCheck(page)
        // The cut buffer is non-empty, so Paste is offered and the selection cleared.
        .elementCountIs('button[aria-label="Paste files"]', 1)
        .elementCountIs('tr input[type="checkbox"]:checked', 0)
        .matches();
    checkConditions(cutDom);
}

// ── Move: Paste into the current folder ────────────────────────────────────
// Run from inside the destination folder. Paste POSTs /move-files (Inertia
// RedirectResponse, 302) and the file appears here with a success flash.
export async function pasteInto(page, { filename, destFolder }) {
    await click(page, 'button[aria-label="Paste files"]:visible');

    const moveReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/move-files")
        .httpCodeIs(302)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");
    await page.locator(`span.truncate:text-is("${filename}")`).waitFor({ timeout: 10000 }).catch(() => {});

    const pasteDom = await domCheck(page)
        .pathnameIs(`/drive/${destFolder}`)
        .elementCountIs(`span.truncate:text-is("${filename}")`, 1)
        // The move buffer emptied, so Paste is no longer offered.
        .elementDoesNotExist('button[aria-label="Paste files"]')
        .elementContains('div[role="alert"]', "Files moved")
        .matches();
    checkConditions(moveReq, pasteDom);
}

// ── Multi-select: Unselect one row ─────────────────────────────────────────
// Toggling a checked row off (`handlerSelectFile`, useSelectionutil.jsx:7-15).
// The bug this catches: the header select-all box is a *separate* boolean
// (`selectAllToggle`) that is never recomputed from the live set (ListView.jsx:47),
// so after a select-all it stays checked while the selection is now partial. Pass
// `expectHeaderChecked` to assert the header's correct state (false when partial).
export async function unselectRow(page, { filename, expectedCheckedRemaining, expectHeaderChecked }) {
    const rowSel = `tr:has(span:text-is("${filename}"))`;
    const checkboxSel = `${rowSel} input[type="checkbox"]`;
    const headSel = 'thead input[type="checkbox"]';

    await click(page, checkboxSel);

    let dom = domCheck(page)
        .elementCountIs(checkboxSel, 1)
        .isNotChecked(checkboxSel)
        // The whole set, not just this row: a toggle that flips the wrong id leaves
        // this row unchecked while the count is off.
        .elementCountIs('tbody input[type="checkbox"]:checked', expectedCheckedRemaining);
    if (expectHeaderChecked === true) dom = dom.isChecked(headSel);
    if (expectHeaderChecked === false) dom = dom.isNotChecked(headSel);
    checkConditions(await dom.matches());
}

// ── Multi-select: Select-all via the header checkbox ───────────────────────
// The thead box drives `handleSelectAllToggle(filesCopy)` (ListView.jsx:43), which
// replaces the set with every rendered id. `expectedRowCount` is the number of
// selectable file rows currently shown (excludes the "Go Up" row, which has no box).
export async function selectAllRows(page, { expectedRowCount }) {
    const headSel = 'thead input[type="checkbox"]';

    await click(page, headSel);

    const dom = await domCheck(page)
        .isChecked(headSel)
        // Every row box is checked, and the count matches the rows shown — a select-all
        // that seeds the set from a stale list would leave one of these wrong.
        .elementCountIs('tbody input[type="checkbox"]', expectedRowCount)
        .elementCountIs('tbody input[type="checkbox"]:checked', expectedRowCount)
        .elementCountIs('tbody input[type="checkbox"]:not(:checked)', 0)
        // Selection > 0 means the bulk toolbar is offered.
        .elementCountIs('button[aria-label="Delete selected files"]:not(.hidden):visible', 1)
        .matches();
    checkConditions(dom);
}

// ── Multi-select: Deselect-all via the header checkbox ─────────────────────
// Clicking a checked header box takes the deselect branch (useSelectionutil.jsx:21):
// the whole selection is cleared. Note this fires whenever `selectAllToggle` is true
// — even after the user manually unchecked a row, so the header can read "checked"
// on a partial selection and one more click clears the lot. That is the intended
// toggle behaviour (a checked box deselects), asserted here as expected, not a bug.
export async function deselectAllRows(page) {
    const headSel = 'thead input[type="checkbox"]';

    await click(page, headSel);

    const dom = await domCheck(page)
        // Header cleared and nothing left checked — the deselect branch ran.
        .isNotChecked(headSel)
        .elementCountIs('tbody input[type="checkbox"]:checked', 0)
        // Empty selection → the bulk toolbar is withdrawn.
        .elementCountIs('button[aria-label="Delete selected files"]:not(.hidden):visible', 0)
        .matches();
    checkConditions(dom);
}

// ── Navigate: Into a nested subfolder (Inertia, state-preserving) ───────────
// Same folder-row link as `navigateIntoFolder`, but the destination is nested, so
// the caller supplies the full `expectedPath` (`navigateIntoFolder` hard-codes a
// root-level `/drive/<name>`). The click is an Inertia visit, so a cut buffer in
// flight survives it — which is what lets a multi-file move cross folders.
export async function enterSubfolder(page, { folderName, expectedPath, expectedItems = [] }) {
    await click(page, `tr:has(span:text-is("${folderName}")) a`);

    const navReq = await networkCheck(page)
        .methodIs("GET")
        .urlContains(expectedPath)
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.waitForURL(new RegExp(expectedPath.replace(/[/]/g, "\\/") + "$"), { timeout: 15000 });
    await page.waitForLoadState("networkidle");

    let navDom = domCheck(page)
        .pathnameIs(expectedPath)
        .textIs('nav[aria-label="Breadcrumb"] [aria-current="page"]', folderName)
        .elementCountIs('button[aria-label="New"]', 1);
    for (const name of expectedItems) {
        navDom = navDom.elementCountIs(`tr:has(span.truncate:text-is("${name}"))`, 1);
    }
    checkConditions(navReq, await navDom.matches());
}

// ── Navigate: Up one level via the "Go Up" link (Inertia, state-preserving) ─
// The ".." row is an Inertia <Link title="Go Up"> (ListView.jsx:96-108) whose href
// is the parent path. Inertia visit → a cut buffer survives, unlike gotoDriveRoot.
export async function navigateUp(page, { expectedPath, expectedItems = [] }) {
    await click(page, 'a[title="Go Up"]');

    const navReq = await networkCheck(page)
        .methodIs("GET")
        .urlContains(expectedPath)
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.waitForURL(new RegExp(expectedPath.replace(/[/]/g, "\\/") + "$"), { timeout: 15000 });
    await page.waitForLoadState("networkidle");

    let navDom = domCheck(page)
        .pathnameIs(expectedPath)
        .elementCountIs('button[aria-label="New"]', 1);
    for (const name of expectedItems) {
        navDom = navDom.elementCountIs(`tr:has(span.truncate:text-is("${name}"))`, 1);
    }
    checkConditions(navReq, await navDom.matches());
}

// ── Move: Paste a multi-file selection into the current folder ──────────────
// Like `pasteInto` but asserts the whole moved set landed here, and that the paste
// affordance emptied. Run from inside the destination. Paste POSTs /move-files
// (Inertia RedirectResponse, 302).
export async function pasteSelectionInto(page, { filenames, destPath }) {
    await click(page, 'button[aria-label="Paste files"]:visible');

    const moveReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/move-files")
        .httpCodeIs(302)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");
    for (const name of filenames) {
        await page.locator(`span.truncate:text-is("${name}")`).waitFor({ timeout: 10000 }).catch(() => {});
    }

    let dom = domCheck(page)
        .pathnameIs(destPath)
        // The move buffer emptied, so Paste is no longer offered.
        .elementDoesNotExist('button[aria-label="Paste files"]')
        .elementContains('div[role="alert"]', "Files moved");
    for (const name of filenames) {
        // Each moved file is present here exactly once — a partial move drops one.
        dom = dom.elementCountIs(`span.truncate:text-is("${name}")`, 1);
    }
    checkConditions(moveReq, await dom.matches());
}

// ── Multi-select: Bulk delete the current selection ────────────────────────
// The toolbar Delete button (`:not(.hidden)` tells it from the row copies) confirms
// first (window.confirm), then POSTs /delete-files (302) with every selected id.
// Asserts exactly the named set is gone and the named siblings survive — scope
// control, so a delete that took the wrong ids is caught.
export async function bulkDeleteSelected(page, { deleted, remaining = [], expectedPath }) {
    page.once("dialog", (dialog) => dialog.accept());
    await click(page, 'button[aria-label="Delete selected files"]:not(.hidden):visible');

    const deleteReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/delete-files")
        .httpCodeIs(302)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");
    for (const name of deleted) {
        await page
            .waitForFunction(
                (n) => [...document.querySelectorAll("span.truncate")].every((s) => s.textContent.trim() !== n),
                name,
                { timeout: 5000 }
            )
            .catch(() => {});
    }

    let dom = domCheck(page)
        .pathnameIs(expectedPath)
        // Selection cleared → the bulk toolbar is gone and nothing is checked.
        .elementCountIs('button[aria-label="Delete selected files"]:not(.hidden):visible', 0)
        .elementCountIs('tbody input[type="checkbox"]:checked', 0);
    for (const name of deleted) {
        dom = dom.elementDoesNotExist(`span.truncate:text-is("${name}")`);
    }
    for (const name of remaining) {
        dom = dom.elementCountIs(`span.truncate:text-is("${name}")`, 1);
    }
    checkConditions(deleteReq, await dom.matches());
}

// ── Create Item: submit expecting a name-collision refusal ─────────────────
// makeFolder/makeFile throw before any DB stat when the name exists
// (FileOperationsService.php:97-98,117-118); the handler flashes the message and
// redirects back (bootstrap/app.php:115-120). Contract: graceful `bg-error` flash,
// 302, and — critically — NO duplicate row (exactly one item keeps the name).
export async function submitCreateItemExpectConflict(page, { itemName, message, expectedCount = 1 }) {
    await click(page, 'button[type="submit"]:visible');

    const req = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/create-item")
        .httpCodeIs(302)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");
    await page.locator('div[role="alert"]', { hasText: message }).first().waitFor({ timeout: 5000 }).catch(() => {});

    const dom = await domCheck(page)
        .elementContains('div[role="alert"]', message)
        .hasClass('div[role="alert"]', "bg-error")
        // The refused create must not have added a second row with the same name.
        .elementCountIs(`span.truncate:text-is("${itemName}")`, expectedCount)
        .elementExists('button[aria-label="New"]')
        .matches();
    expectLaravelError(page, message);
    checkConditions(req, dom);
    // The refusal logs an ERROR at 1-second resolution; settle past that second so
    // the next action's log scope (floored to seconds) cannot re-see it.
    await page.waitForTimeout(1100);
}

// ── Rename: submit expecting a name-collision refusal ──────────────────────
// FileRenameService throws couldNotRename when the target name exists
// (FileRenameService.php:27-29); handled gracefully (bg-error flash, 302). The
// rename must be refused with NO data loss: the file keeps its old name and the
// pre-existing file is untouched — both are still present exactly once.
export async function submitRenameExpectConflict(page, { targetOldName, existingName, message, expectedPath }) {
    const modalSel = '.fixed.inset-0:has(h2:text-is("Rename file"))';

    await click(page, `${modalSel} button[type="submit"]`);

    const req = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/rename-file")
        .httpCodeIs(302)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");
    await page.locator('div[role="alert"]', { hasText: message }).first().waitFor({ timeout: 5000 }).catch(() => {});

    const dom = await domCheck(page)
        .elementContains('div[role="alert"]', message)
        .hasClass('div[role="alert"]', "bg-error")
        // No overwrite, no loss: both names survive exactly once.
        .elementCountIs(`span.truncate:text-is("${targetOldName}")`, 1)
        .elementCountIs(`span.truncate:text-is("${existingName}")`, 1)
        .pathnameIs(expectedPath)
        .matches();
    expectLaravelError(page, message);
    checkConditions(req, dom);
    await page.waitForTimeout(1100);
}

// ── Move: paste expecting a same-name conflict to cancel the whole move ────
// FileMoveService cancels the entire move (no partial, no overwrite) when any
// destination name already exists (FileMoveService.php:47-64). Contract: bg-error
// flash naming the conflict, 302, and the file did NOT land — the destination
// still holds exactly the one pre-existing copy.
export async function pasteExpectConflict(page, { filename, message, destPath }) {
    await click(page, 'button[aria-label="Paste files"]:visible');

    const req = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/move-files")
        .httpCodeIs(302)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");
    await page.locator('div[role="alert"]', { hasText: message }).first().waitFor({ timeout: 5000 }).catch(() => {});

    const dom = await domCheck(page)
        .elementContains('div[role="alert"]', message)
        .hasClass('div[role="alert"]', "bg-error")
        .pathnameIs(destPath)
        // The cancelled move left the pre-existing copy alone and added no second one.
        .elementCountIs(`span.truncate:text-is("${filename}")`, 1)
        .matches();
    expectLaravelError(page, message);
    checkConditions(req, dom);
    await page.waitForTimeout(1100);
}

// ── Share modal: fill expiry (unvalidated type=text; server wants integer) ──
export async function fillShareExpiry(page, { expiry }) {
    await fill(page, "#expiry", expiry);
    const dom = await domCheck(page)
        .inputValueIs("#expiry", expiry)
        .elementExists('button:has-text("Get Sharable Link")')
        .matches();
    checkConditions(dom);
}

// ── Share modal: fill custom slug (server: unique + slug rules) ─────────────
export async function fillShareSlug(page, { slug }) {
    await fill(page, "#slug", slug);
    const dom = await domCheck(page)
        .inputValueIs("#slug", slug)
        .elementExists('button:has-text("Get Sharable Link")')
        .matches();
    checkConditions(dom);
}

// ── Share modal: submit expecting a refusal (bad expiry / slug collision) ──
// Server ValidationException → flashed "Please check the form for errors." + 302
// (bootstrap/app.php:121-126); it is NOT report()ed, so no log allowance needed.
// Contract: no link generated, submit still offered, error flash shown.
export async function getShareLinkExpectError(page, { message = "Please check the form for errors" }) {
    await click(page, 'button:has-text("Get Sharable Link")');
    const req = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/share-files")
        .waitFor({ timeout: 8000 });
    await page.waitForLoadState("networkidle");
    await page.locator('div[role="alert"]', { hasText: message }).first().waitFor({ timeout: 5000 }).catch(() => {});
    const dom = await domCheck(page)
        .elementContains('div[role="alert"]', message)
        .hasClass('div[role="alert"]', "bg-error")
        .elementDoesNotExist('input[readonly]')
        .elementExists('button:has-text("Get Sharable Link")')
        .matches();
    checkConditions(req, dom);
}

// ── Shares management page ─────────────────────────────────────────────────
export async function gotoSharesAll(page, { base }) {
    await goto(page, base + "/shares-all");
    const req = await networkCheck(page)
        .methodIs("GET")
        .urlContains("/shares-all")
        .httpCodeIs(200)
        .waitFor({ timeout: 10000 });
    await page.waitForLoadState("networkidle");
    const dom = await domCheck(page)
        .pathnameIs("/shares-all")
        .htmlContains("All Live Shares")
        .matches();
    checkConditions(req, dom);
}

// The share row on /shares-all is identified by the full link text it prints
// (AllShares.jsx:85-87 = origin + "/shared/" + slug). Pause/Resume/Delete are the
// per-row buttons (labelled at ≥md width, which the default 1280 viewport is).
export async function pauseShareBySlug(page, { slugUrl }) {
    const row = `tr:has(span:text-is("${slugUrl}"))`;
    await click(page, `${row} button:has-text("Pause")`);
    const req = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/share-pause")
        .httpCodeIs(302)
        .waitFor({ timeout: 8000 });
    await page.waitForLoadState("networkidle");
    await page.locator(`${row} button:has-text("Resume")`).first().waitFor({ timeout: 5000 }).catch(() => {});
    const dom = await domCheck(page)
        .elementCountIs(`${row} button:has-text("Resume")`, 1)
        .elementDoesNotExist(`${row} button:has-text("Pause")`)
        .matches();
    checkConditions(req, dom);
}

export async function resumeShareBySlug(page, { slugUrl }) {
    const row = `tr:has(span:text-is("${slugUrl}"))`;
    await click(page, `${row} button:has-text("Resume")`);
    const req = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/share-pause")
        .httpCodeIs(302)
        .waitFor({ timeout: 8000 });
    await page.waitForLoadState("networkidle");
    await page.locator(`${row} button:has-text("Pause")`).first().waitFor({ timeout: 5000 }).catch(() => {});
    const dom = await domCheck(page)
        .elementCountIs(`${row} button:has-text("Pause")`, 1)
        .elementDoesNotExist(`${row} button:has-text("Resume")`)
        .matches();
    checkConditions(req, dom);
}

export async function deleteShareBySlug(page, { slugUrl }) {
    const row = `tr:has(span:text-is("${slugUrl}"))`;
    await click(page, `${row} button:has-text("Delete")`);
    const req = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/share-delete")
        .httpCodeIs(302)
        .waitFor({ timeout: 8000 });
    await page.waitForLoadState("networkidle");
    await page.locator(`span:text-is("${slugUrl}")`).waitFor({ state: "detached", timeout: 5000 }).catch(() => {});
    const dom = await domCheck(page)
        .elementDoesNotExist(`span:text-is("${slugUrl}")`)
        .matches();
    checkConditions(req, dom);
}

// ── Guest: the share is unreachable (paused / expired / deleted) ────────────
// HandleGuestShareMiddleware.php:21-22 redirects any such visit to /login?slug=…,
// NOT a 404 and NOT the password wall. That redirect is the "blocked" contract.
export async function guestVerifyBlocked(page) {
    const dom = await domCheck(page)
        .pathnameIs("/login")
        .elementDoesNotExist("span.truncate")
        .elementDoesNotExist('h1:has-text("Enter Password For Share")')
        .matches();
    checkConditions(dom);
}

// ── Upload a varied set (mix of text + real PNG images) ─────────────────────
// `uploadFiles` only ever makes text/plain fixtures (TestFiles.addText), so a `.png`
// name would still be `file_type === "text"` (libmagic reads content, not the
// extension — see the drive-testing context). This uploads a MIX: `{ name, kind }`
// with `kind: "image"` → a real 1×1 PNG (image/png magic bytes → file_type "image"),
// anything else → a text file. One filechooser action; asserts every uploaded row
// landed exactly once and the row total grew by the batch size (a dropped/duplicated
// upload fails here). `files` = [{ name, kind?, content? }].
export async function uploadVariedFiles(page, { files }) {
    const testFiles = new TestFiles();
    for (const f of files) {
        if (f.kind === "image") testFiles.addImage(f.name);
        else if (f.kind === "html") testFiles.addHtml(f.name, f.content);
        else if (f.kind === "audio") testFiles.addAudio(f.name);
        else if (f.kind === "video") testFiles.addVideo(f.name);
        else if (f.kind === "pdf") testFiles.addPdf(f.name);
        else testFiles.addText(f.name, f.content ?? `content-${f.name}`);
    }
    const names = files.map((f) => f.name);
    const rowsBefore = await page.locator("tr span.truncate").count();

    try {
        const [fileChooser] = await Promise.all([
            page.waitForEvent("filechooser"),
            click(page, 'button[role="menuitem"]:text-is("Upload File")'),
        ]);
        await fileChooser.setFiles(testFiles.paths);

        const uploadReq = await networkCheck(page)
            .methodIs("POST")
            .urlContains("/upload")
            .httpCodeIs(302)
            .waitFor({ timeout: 20000 });
        await page.waitForLoadState("networkidle");
        await page
            .locator('div[role="alert"]:has-text("uploaded")')
            .first()
            .waitFor({ timeout: 15000 })
            .catch(() => {});
        for (const name of names) {
            await page
                .locator(`tr:has(span.truncate:text-is("${name}"))`)
                .waitFor({ timeout: 15000 });
        }

        let dom = domCheck(page).elementCountIs("tr span.truncate", rowsBefore + names.length);
        for (const name of names) {
            dom = dom
                .elementCountIs(`span.truncate:text-is("${name}")`, 1)
                .elementCountIs(`tr:has(span:text-is("${name}"))`, 1);
        }
        const uploadDom = await dom.elementCountIs('button[aria-label="New"]', 1).matches();
        checkConditions(uploadReq, uploadDom);
    } finally {
        testFiles.cleanup();
    }
}

// ── Navigate into a folder whose name holds a `#` (aspect H, REPORT #7) ──────
// The historical bug: FolderItem built the row href by raw string concat, so a
// folder named `h#sh` produced `href="/drive/<parent>/h#sh"`. In a URL a raw `#`
// starts the fragment, so the browser would GET `/drive/<parent>/h` (the `sh` tail
// dropped) — a dead/wrong route to a non-existent folder `h`.
//
// The CORRECT contract (asserted here): clicking the folder must load THAT folder's
// own page — the request carries the `%23`-encoded segment, the breadcrumb reads the
// real name, and "This folder does not exist" is NOT shown. The deployed FolderItem
// now wraps the name in `encodeURIComponent`, so `#`→`%23` and this should hold; if a
// build still raw-concatenated, the GET lands on `/drive/<parent>/h`, the folder is
// missing, the breadcrumb reads the wrong name → these checks go RED, and THAT is the
// finding. The rendered href is logged so the run records exactly what was served.
// A locator `.getAttribute` is a read, not an action — the click that follows carries
// the scope. `encodedSegment` is the `%23`-encoded trailing path segment (e.g.
// `h%23sh`); `expectedItems` are file rows expected inside (empty for a fresh folder).
export async function navigateIntoHashFolder(page, { folderName, encodedSegment, expectedItems = [] }) {
    const rowLink = `tr:has(span:text-is("${folderName}")) a`;
    const renderedHref = await page.locator(rowLink).first().getAttribute("href");
    console.log(`[aspect H] folder "${folderName}" rendered href = ${renderedHref}`);

    await click(page, rowLink);

    const navReq = await networkCheck(page)
        .methodIs("GET")
        .urlContains(encodedSegment)
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");
    await page
        .locator('nav[aria-label="Breadcrumb"] [aria-current="page"]')
        .waitFor({ timeout: 5000 })
        .catch(() => {});

    let dom = domCheck(page)
        // The URL kept the whole encoded segment — not truncated at a raw `#`.
        .pathnameContains(encodedSegment)
        // The folder that loaded is the one clicked, by its real (decoded) name.
        .elementCountIs('nav[aria-label="Breadcrumb"] [aria-current="page"]', 1)
        .textIs('nav[aria-label="Breadcrumb"] [aria-current="page"]', folderName)
        // The dead-route symptom: routing to a missing folder renders this text.
        .htmlDoesNotContain("This folder does not exist")
        .elementCountIs('button[aria-label="New"]', 1);
    for (const name of expectedItems) {
        dom = dom.elementCountIs(`tr:has(span.truncate:text-is("${name}"))`, 1);
    }
    checkConditions(navReq, await dom.matches());
}

// ── Upload queue: shared held-in-flight batch upload (aspect T) ─────────────
// The queue (`useUploadQueue`) coalesces one filechooser batch into ONE entry
// named "<N> files" (getName) and serializes uploads under a navigator lock, so a
// small local batch settles in milliseconds — too fast to observe. This holds the
// `/upload` response with a route delay so the transient `<aside>Uploads</aside>`
// queue dialog is observable in flight; the caller supplies the in-flight DOM
// assertion. The captured in-flight result is checked together with the settled end
// state in one `checkConditions`, so a single action (the filechooser click) owns
// every check. Not exported: the two public helpers below wrap it.
const QUEUE_ASIDE = 'aside:has(h2:text-is("Uploads"))';

// Image/video uploads kick off async thumbnail generation: useThumbnailGenerator
// router.post()s /gen-thumbs (only files+flash), which flashes "Thumbnails generated"
// and consumes it on its Inertia follow-GET. That cycle fires AFTER the upload's own
// settle, so a reload done immediately can race the not-yet-consumed flash and see a
// stale alert. When a batch carried an image, wait the cycle out here so a downstream
// reload starts from a clean session flash. No-op (short, caught) when nothing fired.
async function settleThumbnails(page) {
    await page.waitForResponse((r) => r.url().includes("/gen-thumbs"), { timeout: 6000 }).catch(() => {});
    await page.waitForLoadState("networkidle");
    await page.waitForTimeout(300);
}

async function heldBatchUpload(page, { testFiles, names, inflightCheck, hasImages = false }) {
    const rowsBefore = await page.locator("tr span.truncate").count();
    const hold = async (route) => {
        await new Promise((r) => setTimeout(r, 2500));
        await route.continue();
    };
    await page.route("**/upload", hold);
    try {
        const [fileChooser] = await Promise.all([
            page.waitForEvent("filechooser"),
            click(page, 'button[role="menuitem"]:text-is("Upload File")'),
        ]);
        await fileChooser.setFiles(testFiles.paths);

        // In flight: the queue dialog is up while /upload is held. Capture its DOM
        // now — it detaches once the upload finishes, so this cannot wait.
        await page.locator(QUEUE_ASIDE).waitFor({ timeout: 6000 });
        const inflightDom = await inflightCheck(domCheck(page)).matches();

        const uploadReq = await networkCheck(page)
            .methodIs("POST")
            .urlContains("/upload")
            .httpCodeIs(302)
            .waitFor({ timeout: 25000 });
        await page.waitForLoadState("networkidle");
        // finish() removes the item → the aside drains. A stuck aside is a real bug.
        await page.locator(QUEUE_ASIDE).waitFor({ state: "detached", timeout: 10000 }).catch(() => {});
        for (const name of names) {
            await page.locator(`tr:has(span.truncate:text-is("${name}"))`).waitFor({ timeout: 15000 });
        }

        let settled = domCheck(page)
            .elementCountIs(QUEUE_ASIDE, 0) // queue drained, no leftover entry
            .elementCountIs("tr span.truncate", rowsBefore + names.length);
        for (const name of names) {
            settled = settled
                .elementCountIs(`span.truncate:text-is("${name}")`, 1)
                .elementCountIs(`tr:has(span:text-is("${name}"))`, 1);
        }
        const settledDom = await settled.elementCountIs('button[aria-label="New"]', 1).matches();
        checkConditions(inflightDom, uploadReq, settledDom);

        // Let async thumbnail generation finish so a downstream reload is clean.
        if (hasImages) await settleThumbnails(page);
    } finally {
        await page.unroute("**/upload", hold).catch(() => {});
        testFiles.cleanup();
    }
}

// ── Upload MANY at once and watch the queue entry + progress (aspect T) ──────
// One filechooser batch of mixed files → one queue entry "<N> files" with a live
// progress bar; every file must land exactly once (count delta) and the queue must
// drain. Catches a queue that never renders, drops/duplicates its entry, omits the
// progress bar, mislabels the entry, or fails to drain. `files` = [{ name, kind? }]
// (kind "image" → real 1×1 PNG, else a text fixture). `expectedName` = "<N> files".
export async function uploadWatchQueue(page, { files, expectedName }) {
    const testFiles = new TestFiles();
    for (const f of files) {
        if (f.kind === "image") testFiles.addImage(f.name);
        else testFiles.addText(f.name, f.content ?? `content-${f.name}`);
    }
    await heldBatchUpload(page, {
        testFiles,
        names: files.map((f) => f.name),
        hasImages: files.some((f) => f.kind === "image"),
        inflightCheck: (d) =>
            d
                .elementCountIs(QUEUE_ASIDE, 1)
                // One batch → exactly one entry (0 = dropped, 2 = duplicated).
                .elementCountIs(`${QUEUE_ASIDE} li`, 1)
                .elementCountIs(`${QUEUE_ASIDE} li span.truncate:text-is("${expectedName}")`, 1)
                // The entry left "queued", so its per-entry progress bar renders.
                .elementCountIs(`${QUEUE_ASIDE} progress`, 1)
                .hasAttribute(`${QUEUE_ASIDE} progress`, "aria-label", `${expectedName} upload progress`),
    });
}

// ── Confirm the upload queue exposes NO cancel control (aspect T) ────────────
// The deployed UploadQueueDialog renders only name + status + progress per entry —
// no button — and useUploadQueue exposes only { add, finish, items } (no remove /
// cancel). Cancelling a file mid-upload is therefore not reachable in this build.
// This is the runtime confirmation of that: while an upload is held in flight, the
// queue entry is present but carries ZERO interactive controls and nothing labelled
// cancel/abort. A cancel affordance appearing here would fail the check (and mean the
// sub-aspect became coverable). The upload is then let finish and the file must land.
export async function probeQueueNoCancel(page, { files, expectedName }) {
    const testFiles = new TestFiles();
    for (const f of files) {
        if (f.kind === "image") testFiles.addImage(f.name);
        else testFiles.addText(f.name, f.content ?? `content-${f.name}`);
    }
    await heldBatchUpload(page, {
        testFiles,
        names: files.map((f) => f.name),
        inflightCheck: (d) =>
            d
                .elementCountIs(QUEUE_ASIDE, 1)
                .elementCountIs(`${QUEUE_ASIDE} li`, 1)
                .elementCountIs(`${QUEUE_ASIDE} li span.truncate:text-is("${expectedName}")`, 1)
                // The whole point: no control to cancel/abort/remove the in-flight item.
                .elementCountIs(`${QUEUE_ASIDE} button`, 0)
                .elementDoesNotExist(`${QUEUE_ASIDE} [aria-label*="ancel"]`)
                .elementDoesNotExist(`${QUEUE_ASIDE} [title*="ancel"]`),
    });
}

// ── Drag-drop files onto the window DropZone (aspect T) ──────────────────────
// DropZone mounts a fullscreen overlay only while a Files drag is active
// (isDragActive, set from a window "dragenter"); react-dropzone's onDrop on that
// overlay routes accepted files through the same uploadQueue → POST /upload path as
// the menu upload. A real OS drag cannot be driven headless, so this synthesises it:
// a "dragenter" carrying a Files DataTransfer makes the overlay mount, then a "drop"
// on that overlay carries the real file bytes (confirmed to reach /upload 302 at
// runtime). The scripted "drop" is not a wrapper action, so the scope is opened by
// hand immediately before it. Catches: the drop path not wiring to /upload, files
// not landing, or the overlay not dismissing after the drop.
export async function dropFilesOnZone(page, { files }) {
    const testFiles = new TestFiles();
    for (const f of files) {
        if (f.kind === "image") testFiles.addImage(f.name);
        else testFiles.addText(f.name, f.content ?? `drop-${f.name}`);
    }
    const names = files.map((f) => f.name);
    const payloads = testFiles.paths.map((p, i) => ({
        name: names[i],
        type: names[i].endsWith(".png") ? "image/png" : "text/plain",
        b64: readFileSync(p).toString("base64"),
    }));
    const rowsBefore = await page.locator("tr span.truncate").count();
    const overlayGone = 'div:has-text("here to upload")';

    try {
        // 1) A Files "dragenter" makes the overlay (react-dropzone root) mount.
        await page.evaluate(() => {
            const dt = new DataTransfer();
            dt.items.add(new File(["x"], "probe", { type: "text/plain" }));
            window.dispatchEvent(new DragEvent("dragenter", { dataTransfer: dt, bubbles: true }));
        });
        await page.waitForFunction(
            () =>
                [...document.querySelectorAll("div")].some(
                    (d) => d.style && d.style.position === "fixed" && /here to upload/.test(d.textContent),
                ),
            { timeout: 5000 },
        );

        // 2) The "drop" on that overlay carries the real files → /upload.
        setLastActionTime(page);
        await page.evaluate(async (items) => {
            const dt = new DataTransfer();
            for (const it of items) {
                const bin = atob(it.b64);
                const arr = new Uint8Array(bin.length);
                for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
                dt.items.add(new File([arr], it.name, { type: it.type }));
            }
            const overlay = [...document.querySelectorAll("div")].find(
                (d) => d.style && d.style.position === "fixed" && /here to upload/.test(d.textContent),
            );
            overlay.dispatchEvent(new DragEvent("drop", { dataTransfer: dt, bubbles: true, cancelable: true }));
        }, payloads);

        const uploadReq = await networkCheck(page)
            .methodIs("POST")
            .urlContains("/upload")
            .httpCodeIs(302)
            .waitFor({ timeout: 25000 });
        await page.waitForLoadState("networkidle");
        await page.locator(overlayGone).first().waitFor({ state: "detached", timeout: 5000 }).catch(() => {});
        for (const name of names) {
            await page.locator(`tr:has(span.truncate:text-is("${name}"))`).waitFor({ timeout: 15000 });
        }

        let dom = domCheck(page)
            // The drag overlay dismissed on drop (setIsDragActive(false)).
            .elementCountIs(overlayGone, 0)
            .elementCountIs("tr span.truncate", rowsBefore + names.length);
        for (const name of names) {
            dom = dom
                .elementCountIs(`span.truncate:text-is("${name}")`, 1)
                .elementCountIs(`tr:has(span:text-is("${name}"))`, 1);
        }
        const dropDom = await dom.elementCountIs('button[aria-label="New"]', 1).matches();
        checkConditions(uploadReq, dropDom);

        // A dropped image starts thumbnail generation; let it finish before returning
        // so a downstream reload does not race the "Thumbnails generated" flash.
        if (files.some((f) => f.kind === "image")) await settleThumbnails(page);
    } finally {
        testFiles.cleanup();
    }
}

// ── Encrypted upload: open the modal ────────────────────────────────────────
// The "Upload Encrypted" menu item mounts PasswordProtectedUploadModal (a
// `.fixed.inset-0` Modal titled "Upload Encrypted") with a prefilled #zipName, the
// #ppPassword / #ppConfirmPassword fields, and an "Upload" submit. Call openNewMenu
// first. Fails if the modal or any field is missing (feature not deployed / renamed).
export async function openEncryptedUploadModal(page) {
    const modal = '.fixed.inset-0:has(h2:text-is("Upload Encrypted"))';
    await click(page, 'button[role="menuitem"]:text-is("Upload Encrypted")');

    const dom = await domCheck(page)
        .elementCountIs(modal, 1)
        .elementCountIs("#zipName", 1)
        .inputValueContains("#zipName", "protected_") // the auto-suggested name
        .elementCountIs("#ppPassword", 1)
        .elementCountIs("#ppConfirmPassword", 1)
        .elementCountIs(`${modal} button[type="submit"]`, 1)
        .matches();
    checkConditions(dom);
}

// ── Encrypted upload: pick the plaintext files to encrypt ───────────────────
// "Select Files" opens the modal's #ppFileInput. The chosen files render as
// top-level entries in the modal list; NO /upload fires yet (encryption is on
// submit). zip.js reads the file bytes lazily at submit time, so the temp fixtures
// MUST outlive this call — it does NOT clean up. It returns the TestFiles handle for
// `submitEncryptedUpload` to dispose once the zip has been built and uploaded.
// Asserts each name appears as an entry and nothing uploaded on selection.
export async function selectEncryptedFiles(page, { filenames, contents = [] }) {
    const modal = '.fixed.inset-0:has(h2:text-is("Upload Encrypted"))';
    const testFiles = new TestFiles();
    filenames.forEach((n, i) => testFiles.addText(n, contents[i] ?? `enc-${i}`));

    const [fileChooser] = await Promise.all([
        page.waitForEvent("filechooser"),
        click(page, `${modal} button:has-text("Select Files")`),
    ]);
    await fileChooser.setFiles(testFiles.paths);
    await page
        .locator(`${modal} span.truncate:text-is("${filenames[0]}")`)
        .waitFor({ timeout: 5000 })
        .catch(() => {});

    let dom = domCheck(page).elementCountIs(modal, 1);
    for (const n of filenames) {
        dom = dom.elementCountIs(`${modal} span.truncate:text-is("${n}")`, 1);
    }
    // Selecting must not upload anything yet — the zip is built on submit.
    const net = await networkCheck(page).urlDoesNotContain("/upload").matches();
    checkConditions(await dom.matches(), net);

    return testFiles;
}

// ── Encrypted upload: fill the zip name ─────────────────────────────────────
export async function fillEncryptedZipName(page, { zipName }) {
    await fill(page, "#zipName", zipName);

    const dom = await domCheck(page)
        .inputValueIs("#zipName", zipName)
        .hasFocus("#zipName")
        .matches();
    checkConditions(dom);
}

// ── Encrypted upload: fill the password ─────────────────────────────────────
export async function fillEncryptedPassword(page, { password }) {
    await fill(page, "#ppPassword", password);

    const dom = await domCheck(page)
        .inputValueIs("#ppPassword", password)
        // The confirm field this fill did not touch is still empty.
        .inputValueIs("#ppConfirmPassword", "")
        .matches();
    checkConditions(dom);
}

// ── Encrypted upload: confirm the password ──────────────────────────────────
// Both fields now hold the same value, so the modal's validate() will pass on
// submit (mismatch would flash "Passwords do not match." and never post).
export async function fillEncryptedConfirm(page, { password }) {
    await fill(page, "#ppConfirmPassword", password);

    const dom = await domCheck(page)
        .inputValueIs("#ppConfirmPassword", password)
        .inputValueIs("#ppPassword", password)
        .matches();
    checkConditions(dom);
}

// ── Encrypted upload: submit → client AES-256 zip → POST /upload ────────────
// zip.js zips + AES-256-encrypts the selection in-browser, then router.post uploads
// the single "<zipName>.zip" container. Asserts the encrypted artifact lands exactly
// once, the plaintext inner file is NOT exposed as a row, the modal closes, and the
// row total grew by exactly one. Decryptability is not asserted (unverifiable here).
export async function submitEncryptedUpload(page, { zipFilename, innerNames = [], expectedPath, fixtures }) {
    const modal = '.fixed.inset-0:has(h2:text-is("Upload Encrypted"))';
    const rowsBefore = await page.locator("tr span.truncate").count();

    await click(page, `${modal} button[type="submit"]`);

    try {
        const uploadReq = await networkCheck(page)
            .methodIs("POST")
            .urlContains("/upload")
            .httpCodeIs(302)
            .waitFor({ timeout: 30000 });
        await page.waitForLoadState("networkidle");
        await page.locator(modal).waitFor({ state: "detached", timeout: 8000 }).catch(() => {});
        await page
            .locator(`tr:has(span.truncate:text-is("${zipFilename}"))`)
            .waitFor({ timeout: 15000 })
            .catch(() => {});

        let dom = domCheck(page)
            .elementCountIs(modal, 0) // modal closed after onFinish
            .elementCountIs(`span.truncate:text-is("${zipFilename}")`, 1) // the .zip landed once
            .elementCountIs("tr span.truncate", rowsBefore + 1) // exactly one new row
            .pathnameIs(expectedPath);
        for (const inner of innerNames) {
            // The plaintext was zipped client-side, not uploaded raw — never a row.
            dom = dom.elementCountIs(`span.truncate:text-is("${inner}")`, 0);
        }
        const encDom = await dom.elementCountIs('button[aria-label="New"]', 1).matches();
        checkConditions(uploadReq, encDom);
    } finally {
        // The plaintext fixtures survived from selectEncryptedFiles so zip.js could
        // read them at submit; dispose them now.
        fixtures?.cleanup();
    }
}

// ── Guest: Enter Wrong Password (refusal) ──────────────────────────────────
// A wrong password must NOT let the guest in: the POST is refused (302 back to the
// wall), the error flash shows, and no file list leaks. `throttle:shared` is 20/min
// by IP (AppServiceProvider), so callers must not spam this. The message is flashed
// via a PersonalDriveException; `dontReport(PersonalDriveException)` (bootstrap/app.php:70)
// means it should NOT hit the ERROR log, but a one-shot allowance is kept in case a
// build still report()s it — harmless when nothing is logged.
export async function guestEnterWrongPassword(page, { password, message = "Wrong password" }) {
    await page.fill('input[type="password"]', password);
    await click(page, 'button[type="submit"]:has-text("Submit")');

    const req = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/shared-check-password")
        .httpCodeIs(302)
        .waitFor({ timeout: 8000 });
    await page.waitForLoadState("networkidle");
    await page.locator('div[role="alert"]', { hasText: message }).first().waitFor({ timeout: 5000 }).catch(() => {});

    const dom = await domCheck(page)
        // Still on the wall: the gate held.
        .elementExists('h1:has-text("Enter Password For Share")')
        .elementExists('input[type="password"]')
        // No files leaked to an unauthenticated guest.
        .elementDoesNotExist('span.truncate')
        // The refusal is surfaced to the user.
        .elementContains('div[role="alert"]', message)
        .hasClass('div[role="alert"]', "bg-error")
        .matches();
    expectLaravelError(page, message);
    checkConditions(req, dom);
    await page.waitForTimeout(1100);
}

// ── Guest: Enter A Subfolder Within The Share ──────────────────────────────
// The folder row is an Inertia <Link> to /shared/{slug}/{path} (FolderItem.jsx:30-45),
// exercising the {path?} `.*` route (routes/web.php:111-114). Asserts the nested view
// loads (GET 200), the breadcrumb's current crumb is this folder (Breadcrumb.jsx:45-56),
// the expected children render, the "Go Up" row is present (ListView.jsx:90-112 shows
// it once below the share root), and NO owner upload menu appears (isAdmin false).
export async function guestEnterSubfolder(page, { folderName, expectedPath, expectedItems = [], absent = [] }) {
    await click(page, `tr:has(span:text-is("${folderName}")) a`);

    const navReq = await networkCheck(page)
        .methodIs("GET")
        .urlContains(expectedPath)
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.waitForURL(new RegExp(expectedPath.replace(/[/]/g, "\\/") + "$"), { timeout: 15000 });
    await page.waitForLoadState("networkidle");

    let dom = domCheck(page)
        .pathnameIs(expectedPath)
        .textIs('nav[aria-label="Breadcrumb"] [aria-current="page"]', folderName)
        .elementExists('a[title="Go Up"]')
        .elementDoesNotExist('button[aria-label="New"]');
    for (const name of expectedItems) {
        dom = dom.elementCountIs(`tr:has(span.truncate:text-is("${name}"))`, 1);
    }
    for (const name of absent) {
        dom = dom.elementDoesNotExist(`span.truncate:text-is("${name}")`);
    }
    checkConditions(navReq, await dom.matches());
}

// ── Guest: Navigate Up One Level ("Go Up" row) ─────────────────────────────
// The ".." row is an Inertia <Link title="Go Up"> to the parent path (ListView.jsx:96-108).
// `atShareRoot` asserts the Go Up row is GONE at /shared/{slug} (ListView.jsx:90-93 hides
// it once the path matches the share-root regex). Still no owner upload menu for a guest.
export async function guestNavigateUp(page, { expectedPath, expectedItems = [], atShareRoot = false }) {
    await click(page, 'a[title="Go Up"]');

    const navReq = await networkCheck(page)
        .methodIs("GET")
        .urlContains(expectedPath)
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.waitForURL(new RegExp(expectedPath.replace(/[/]/g, "\\/") + "$"), { timeout: 15000 });
    await page.waitForLoadState("networkidle");

    let dom = domCheck(page)
        .pathnameIs(expectedPath)
        .elementDoesNotExist('button[aria-label="New"]');
    if (atShareRoot) dom = dom.elementDoesNotExist('a[title="Go Up"]');
    for (const name of expectedItems) {
        dom = dom.elementCountIs(`tr:has(span.truncate:text-is("${name}"))`, 1);
    }
    checkConditions(navReq, await dom.matches());
}

// ── Guest: Preview A Text File (read-only, no edit) ────────────────────────
// Clicking the file opens MediaViewer → TxtViewer, which axios-GETs the body from
// /fetch-file/{id}/{slug} (TxtViewer.jsx:108-116 — the slug proves the guest-authorized
// fetch). Asserts the body renders in a read-only <pre>, and — since startEditing is
// `isAdmin`-gated (TxtViewer.jsx:87-90) — that the guest has NO edit affordance: the
// <pre> lacks the `cursor-pointer` click-to-edit hint and no <textarea>/Save exists.
export async function guestPreviewText(page, { label, content, slug }) {
    await click(page, `tr:has(span.truncate:text-is("${label}")) div[data-file-id]`);

    const fetchReq = await networkCheck(page)
        .methodIs("GET")
        .urlContains("/fetch-file/")
        .urlContains(slug)
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.locator(`.fixed.inset-0 pre:has-text("${content}")`).waitFor({ timeout: 8000 }).catch(() => {});

    const dom = await domCheck(page)
        .elementCountIs(".fixed.inset-0", 1)
        .elementCountIs(".fixed.inset-0 pre", 1)
        // The right file's body — a wrong-id preview would show other content.
        .elementContains(".fixed.inset-0 pre", content)
        // Guest = isAdmin false: no click-to-edit, no edit box, no Save.
        .doesNotHaveClass(".fixed.inset-0 pre", "cursor-pointer")
        .elementDoesNotExist(".fixed.inset-0 textarea")
        .elementDoesNotExist('.fixed.inset-0 button:has-text("Save")')
        .pathnameDoesNotContain("/login")
        .matches();
    checkConditions(fetchReq, dom);
}

// ── Guest: Preview An Image ────────────────────────────────────────────────
// MediaViewer → ImageViewer renders <img alt="Selected File" src="/fetch-file/{id}/{slug}">
// (ImageViewer.jsx). Asserts the image element mounts, the slug-authorized fetch returns
// 200, and no editor surfaces for a guest.
export async function guestPreviewImage(page, { label, slug }) {
    await click(page, `tr:has(span.truncate:text-is("${label}")) div[data-file-id]`);

    const fetchReq = await networkCheck(page)
        .methodIs("GET")
        .urlContains("/fetch-file/")
        .urlContains(slug)
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.locator('.fixed.inset-0 img[alt="Selected File"]').waitFor({ timeout: 8000 }).catch(() => {});

    const dom = await domCheck(page)
        .elementCountIs(".fixed.inset-0", 1)
        .elementCountIs('.fixed.inset-0 img[alt="Selected File"]', 1)
        .elementDoesNotExist(".fixed.inset-0 textarea")
        .elementDoesNotExist('.fixed.inset-0 button:has-text("Save")')
        .pathnameDoesNotContain("/login")
        .matches();
    checkConditions(fetchReq, dom);
}

// ── Guest: Preview An HTML File ────────────────────────────────────────────
// MediaViewer → HtmlViewer renders <iframe title="HTML Content" src="/fetch-file/{id}/{slug}">
// (HtmlViewer.jsx). Asserts the iframe mounts, the slug-authorized fetch returns 200,
// and no editor surfaces for a guest.
export async function guestPreviewHtml(page, { label, slug }) {
    await click(page, `tr:has(span.truncate:text-is("${label}")) div[data-file-id]`);

    const fetchReq = await networkCheck(page)
        .methodIs("GET")
        .urlContains("/fetch-file/")
        .urlContains(slug)
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.locator('.fixed.inset-0 iframe[title="HTML Content"]').waitFor({ timeout: 8000 }).catch(() => {});

    const dom = await domCheck(page)
        .elementCountIs(".fixed.inset-0", 1)
        .elementCountIs('.fixed.inset-0 iframe[title="HTML Content"]', 1)
        .elementDoesNotExist(".fixed.inset-0 textarea")
        .elementDoesNotExist('.fixed.inset-0 button:has-text("Save")')
        .pathnameDoesNotContain("/login")
        .matches();
    checkConditions(fetchReq, dom);
}

// ── Guest: Close The Preview (Escape) ──────────────────────────────────────
// Escape is the viewer's own binding (MediaViewer.jsx handleKeyDown). No wrapper
// covers a key press, so the scope is opened by hand. The overlay must unmount, no
// owner upload menu appears, and the row is still listed underneath (guest path,
// not bounced to /login).
export async function guestClosePreview(page, { label }) {
    setLastActionTime(page);
    await page.keyboard.press("Escape");
    await page.waitForTimeout(500);

    const dom = await domCheck(page)
        .elementCountIs(".fixed.inset-0", 0)
        .elementCountIs('button[aria-label="New"]', 0)
        .elementCountIs(`tr:has(span.truncate:text-is("${label}"))`, 1)
        .pathnameDoesNotContain("/login")
        .matches();
    checkConditions(dom);
}

// ── Guest: Reload an authenticated share expecting it to be BLOCKED ─────────
// After the owner pauses (or deletes) the share, a reload of /shared/{slug} is bounced
// by HandleGuestShareMiddleware.php:21-22 to /login?slug=… (not a 404, not the wall).
// That redirect — with no file list and no password wall — is the "blocked" contract.
export async function reloadGuestShareBlocked(page) {
    await reload(page);
    await page.waitForURL(/\/login/, { timeout: 10000 });
    await page.waitForLoadState("networkidle");

    const dom = await domCheck(page)
        .pathnameIs("/login")
        .elementDoesNotExist("span.truncate")
        .elementDoesNotExist('h1:has-text("Enter Password For Share")')
        .matches();
    checkConditions(dom);
}

// ── Guest: Navigate (goto) back to a resumed share and re-verify ────────────
// After a pause bounced the guest to /login, a resume must let the SAME session back in
// without a password re-prompt (the `shared_{slug}_authenticated` session flag survives).
// A full goto (not reload — the guest is on /login) re-fetches /shared/{slug} (GET 200)
// and the files are listed again.
export async function gotoGuestShareVerify(page, { url, filenames, absent = [] }) {
    await goto(page, url);
    const req = await networkCheck(page)
        .methodIs("GET")
        .urlContains("/shared/")
        .httpCodeIs(200)
        .waitFor({ timeout: 10000 });
    await page.waitForLoadState("networkidle");
    checkConditions(req);

    await guestVerifyFiles(page, { filenames, absent });
}

// ════════════════════════════════════════════════════════════════════════════
// TILE / GRID VIEW + THUMBNAILS (aspects S + R)
//
// The drive renders one of two mutually-exclusive views from the `viewMode`
// localStorage key (FileBrowserSection.jsx:220-228, values "ListView" |
// "TileViewOne"; default "ListView"). The tile toggle buttons carry
// aria-label "Tile view" / "List view"; the active one gets `bg-gray-900`
// + `border-blue-300` (FileBrowserSection.jsx:443-461). The whole ListView
// row library above assumes the <table>; tile view renders a grid of
// FileTileViewCard instead, so these helpers use the CARD markup discovered
// at runtime — NOT the ListView row selectors.
//
// Tile card facts (FileTileViewCard.jsx, confirmed at runtime):
//   • A FILE card root carries `data-file-id="<id>"` (folders get none), so
//     `[data-file-id]` is the exact, stable file-card set.
//   • Selecting: the card's header holds one `input[type=checkbox]`; the
//     wrapping div's onClick calls handlerSelectFile. `isSelected` adds
//     `bg-gray-950` to the card root and checks the box.
//   • Thumbnail: an image/video card with `has_thumbnail` renders
//     `<img alt="Thumbnail" src="/fetch-thumb/<id>">`; otherwise (and for any
//     non-thumbnailable file) it renders the lucide `<File>` fallback
//     (`svg.lucide-file`). That present/absent split is the render DELTA.
//   • The per-card action cluster (Delete/Share/Favorite/Rename/Download) is in
//     an `absolute … hidden md:group-hover:flex` container, so it is present but
//     NOT visible until the card is hovered — the per-card copies stay invisible
//     during a bulk action, which is why the toolbar's `:not(.hidden):visible`
//     copy stays the single actionable one, exactly as in list view.
//   • The card's own favorite star uses aria-label "Add to favorites" /
//     "Already a favorite" (FavoriteButton.jsx), distinct from the bulk toolbar
//     favorite ("Add selected items to favorites").

const tileCard = (name) => `div.group:has(h3[title="${name}"])`;

// Build a checkConditions-compatible result for a non-DOM/non-network assertion
// (thumbnail batch counts / guard halt). `checks` is an array of
// { name, passed, expected, actual }. checkConditions treats it exactly like a
// dom/network result: it throws + prints the failing checks and runs
// assertNoErrors on `page`.
function customResult(page, type, checks) {
    return { type, page, passed: checks.every((c) => c.passed), checks };
}

// ── Ambient state: read the current viewMode (util, not an action) ──────────
// Captured at setup so cleanup can restore whatever the profile started with.
export async function readViewMode(page) {
    return await page.evaluate(() => localStorage.getItem("viewMode"));
}

// ── Ambient state: read every FILE card's filename→id map (util) ────────────
// Reads `[data-file-id]` cards and their h3 title. A read, not an action.
export async function readTileFileIds(page) {
    return await page.evaluate(() => {
        const out = {};
        for (const el of document.querySelectorAll("[data-file-id]")) {
            const h3 = el.querySelector("h3[title]");
            if (h3) out[h3.getAttribute("title")] = el.getAttribute("data-file-id");
        }
        return out;
    });
}

// ── View toggle: switch to TILE view ───────────────────────────────────────
// The action is the toggle click. Asserts the markup actually swapped (the
// <table> is gone, the grid is present, the Tile button is the active one) and
// — when `checkedIds`/`total` are given — that a selection made in the other
// view SURVIVED the swap (the cross-view state bug class: selectedFiles lives in
// FileBrowserSection and must be shared by both renderers).
export async function switchToTileView(page, { fileCardCount, checkedIds = null, total = null }) {
    await click(page, 'button[aria-label="Tile view"]');
    await page.waitForLoadState("networkidle");
    await page.locator("[data-file-id]").first().waitFor({ timeout: 5000 }).catch(() => {});

    let dom = domCheck(page)
        .hasClass('button[aria-label="Tile view"]', "bg-gray-900")
        .hasClass('button[aria-label="Tile view"]', "border-blue-300")
        .doesNotHaveClass('button[aria-label="List view"]', "bg-gray-900")
        // The list <table> renderer must be gone — the grid replaced it.
        .elementCountIs("table", 0)
        .elementCountIs("[data-file-id]", fileCardCount);
    if (total !== null) dom = dom.elementCountIs('[data-file-id] input[type="checkbox"]:checked', total);
    for (const id of checkedIds ?? []) {
        dom = dom.elementCountIs(`[data-file-id="${id}"] input[type="checkbox"]:checked`, 1);
    }
    checkConditions(await dom.matches());
}

// ── View toggle: switch to LIST view ────────────────────────────────────────
// Mirror of the above. With `checkedNames`/`total`, asserts a tile-view
// selection survived the swap into list markup (same ids checked as rows).
export async function switchToListView(page, { rowCount = null, checkedNames = null, total = null }) {
    await click(page, 'button[aria-label="List view"]');
    await page.waitForLoadState("networkidle");
    await page.locator("table").first().waitFor({ timeout: 5000 }).catch(() => {});

    let dom = domCheck(page)
        .hasClass('button[aria-label="List view"]', "bg-gray-900")
        .doesNotHaveClass('button[aria-label="Tile view"]', "bg-gray-900")
        // ListView renders a <table>; TileViewOne does not — that is the render
        // discriminator. (`[data-file-id]` is NOT tile-only: FileItem.jsx:29 puts
        // it on the list row's filename cell too, so it can't mark the view.)
        .elementCountIs("table", 1);
    if (rowCount !== null) dom = dom.elementCountIs("tbody tr:has(input[type=\"checkbox\"])", rowCount);
    if (total !== null) dom = dom.elementCountIs('tbody input[type="checkbox"]:checked', total);
    for (const name of checkedNames ?? []) {
        dom = dom.elementCountIs(`tr:has(span:text-is("${name}")) input[type="checkbox"]:checked`, 1);
    }
    checkConditions(await dom.matches());
}

// ── Tile: select a card ─────────────────────────────────────────────────────
// Clicks the card's checkbox (bubbles to the card div's handlerSelectFile). The
// checkbox `checked` + the `bg-gray-950` root class are the app's own selection
// indicators; the total checked-card count is the SCOPE proof (the selection is
// a claim about the whole set — a mis-select would leave the total right but the
// wrong card checked, which the per-card check catches, or the wrong total,
// which the count catches). With a selection live and isAdmin, the shared bulk
// toolbar renders exactly one visible Delete/Share/Download (its per-card copies
// stay in the hidden hover cluster) — parity with list view.
export async function selectTileCard(page, { filename, expectedSelectedTotal }) {
    const cardSel = tileCard(filename);
    const boxSel = `${cardSel} input[type="checkbox"]`;
    const checkedBefore = await page.locator('[data-file-id] input[type="checkbox"]:checked').count();
    await click(page, boxSel);

    const dom = await domCheck(page)
        .isChecked(boxSel)
        .hasClass(cardSel, "bg-gray-950")
        .elementCountIs('[data-file-id] input[type="checkbox"]:checked', expectedSelectedTotal)
        .elementCountIs(`${cardSel} input[type="checkbox"]:checked`, 1)
        // The selection registered a real change, not a no-op re-render.
        .elementCountIsNot('[data-file-id] input[type="checkbox"]:checked', checkedBefore)
        // Shared bulk toolbar — same controls the list view surfaces on selection.
        // The tile per-card copies of Delete/Share are icon-only and live in a
        // `hidden md:group-hover:flex` CONTAINER (the button carries no `.hidden`
        // itself), and clicking a card's checkbox hovers that card, so its per-card
        // copies also go :visible. The TOOLBAR copies are the only ones that render
        // a text label ("Delete"/"Share", `hidden lg:inline`), so :has-text singles
        // them out; the bulk-favorite label is unique to the toolbar already.
        .elementCountIs('button[aria-label="Delete selected files"]:has-text("Delete"):visible', 1)
        .elementCountIs('button[aria-label="Share selected files"]:has-text("Share"):visible', 1)
        .elementCountIs('button[aria-label="Add selected items to favorites"]:visible', 1)
        .matches();
    checkConditions(dom);
}

// ── Tile: unselect a card (churn) ───────────────────────────────────────────
export async function unselectTileCard(page, { filename, expectedSelectedTotal }) {
    const cardSel = tileCard(filename);
    const boxSel = `${cardSel} input[type="checkbox"]`;
    await click(page, boxSel);

    const dom = await domCheck(page)
        .isNotChecked(boxSel)
        .doesNotHaveClass(cardSel, "bg-gray-950")
        .elementCountIs('[data-file-id] input[type="checkbox"]:checked', expectedSelectedTotal)
        .elementCountIs(`${cardSel} input[type="checkbox"]:checked`, 0)
        .matches();
    checkConditions(dom);
}

// ── Tile: hover a card to reveal its action cluster ─────────────────────────
// The per-card star lives in `hidden md:group-hover:flex`, so it is present but
// not actionable until the card is hovered. `label` is the star's current
// aria-label ("Add to favorites" before, "Already a favorite" after).
export async function hoverTileCard(page, { filename, label = "Add to favorites" }) {
    const cardSel = tileCard(filename);
    await hover(page, cardSel);

    const dom = await domCheck(page)
        .elementCountIs(`${cardSel} button[aria-label="${label}"]:visible`, 1)
        .matches();
    checkConditions(dom);
}

// ── Tile: favorite a card via its own star ──────────────────────────────────
// Hover first (hoverTileCard). Clicking the star runs onAddFavorite(id) →
// toggleFavorites([id]) → axios POST /favorites (JSON 200, FileBrowserSection.jsx:136).
// The DELTA: the star flips from "Add to favorites" to "Already a favorite" and
// gains `bg-blue-700`; the "Add to favorites" affordance for THIS card is gone.
// Persistence + scope (only this file, a sibling untouched) is proven separately
// through the favorites menu (openFavoritesMenu).
export async function favoriteTileCard(page, { filename }) {
    const cardSel = tileCard(filename);
    await click(page, `${cardSel} button[aria-label="Add to favorites"]`);

    const favReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/favorites")
        .httpCodeIs(200)
        .waitFor({ timeout: 10000 });
    await page.waitForLoadState("networkidle");

    const dom = await domCheck(page)
        .elementCountIs(`${cardSel} button[aria-label="Already a favorite"]`, 1)
        .hasClass(`${cardSel} button[aria-label="Already a favorite"]`, "bg-blue-700")
        .elementCountIs(`${cardSel} button[aria-label="Add to favorites"]`, 0)
        .matches();
    checkConditions(favReq, dom);
}

// ── Tile: bulk-favorite the current multi-selection ─────────────────────────
// The toolbar's own "Add selected items to favorites" (handleAddFavorites →
// toggleFavorites(Array.from(selectedFiles), true)) POSTs /favorites for the
// not-yet-favorited ids and then CLEARS the selection (clearSelection=true).
// Asserts the request fired and the selection emptied (toolbar gone, nothing
// checked). WHICH files got starred is verified via the favorites menu by the
// caller (scope + persistence).
export async function bulkFavoriteTiles(page) {
    await click(page, 'button[aria-label="Add selected items to favorites"]:visible');

    const favReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/favorites")
        .httpCodeIs(200)
        .waitFor({ timeout: 10000 });
    await page.waitForLoadState("networkidle");

    const dom = await domCheck(page)
        .elementCountIs('[data-file-id] input[type="checkbox"]:checked', 0)
        .elementCountIs('button[aria-label="Add selected items to favorites"]:visible', 0)
        .matches();
    checkConditions(favReq, dom);
}

// ── Tile: bulk-delete the current multi-selection ───────────────────────────
// The shared toolbar Delete (its visible "Delete" text label singles it out from
// the icon-only per-card copies that go :visible on card hover) confirms, then
// selected id. Tile-aware scope: exactly the `deletedIds` cards are gone, every
// `remainingIds` card survives, and the selection cleared — so a delete that
// took the wrong ids (or over-reached to siblings) fails here.
export async function bulkDeleteTiles(page, { deletedIds, remainingIds = [], folderPath }) {
    page.once("dialog", (d) => d.accept());
    await click(page, 'button[aria-label="Delete selected files"]:has-text("Delete"):visible');

    const delReq = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/delete-files")
        .httpCodeIs(302)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");
    for (const id of deletedIds) {
        await page.locator(`[data-file-id="${id}"]`).waitFor({ state: "detached", timeout: 5000 }).catch(() => {});
    }

    let dom = domCheck(page)
        .pathnameIs(folderPath)
        .elementCountIs('button[aria-label="Delete selected files"]:has-text("Delete"):visible', 0)
        .elementCountIs('[data-file-id] input[type="checkbox"]:checked', 0);
    for (const id of deletedIds) dom = dom.elementCountIs(`[data-file-id="${id}"]`, 0);
    for (const id of remainingIds) dom = dom.elementCountIs(`[data-file-id="${id}"]`, 1);
    checkConditions(delReq, await dom.matches());
}

// ── Tile: reload and verify the thumbnail render DELTA (aspects S) ──────────
// The action is the reload. For every image/video card the thumbnail must
// actually resolve — `<img alt="Thumbnail" src="/fetch-thumb/<id>">` present and
// its `naturalWidth` non-zero (a broken/404 thumb decodes to 0, so this catches a
// broken-thumbnail render, not just a missing <img>). For every fallback card the
// <img> is ABSENT and the lucide `<File>` icon (`svg.lucide-file`) is shown. The
// present-only-for-image split is the delta; asserting it after a reload also
// proves the generated thumbnails PERSIST server-side.
export async function reloadTileVerifyThumbnails(page, { folderPath, imageIds, fallbackIds }) {
    await reload(page);
    await page.waitForLoadState("networkidle");
    await page.locator("[data-file-id]").first().waitFor({ timeout: 8000 }).catch(() => {});
    // Force any lazy <img> into view so naturalWidth reflects the real fetch.
    for (const id of imageIds) {
        await page.locator(`[data-file-id="${id}"] img[alt="Thumbnail"]`).scrollIntoViewIfNeeded({ timeout: 3000 }).catch(() => {});
    }
    await page.waitForTimeout(400);

    let dom = domCheck(page).pathnameIs(folderPath);
    for (const id of imageIds) {
        const imgSel = `[data-file-id="${id}"] img[alt="Thumbnail"]`;
        dom = dom
            .elementCountIs(imgSel, 1)
            .hasAttribute(imgSel, "src", `/fetch-thumb/${id}`)
            .propertyIsNot(imgSel, "naturalWidth", 0);
    }
    for (const id of fallbackIds) {
        dom = dom
            .elementCountIs(`[data-file-id="${id}"] img[alt="Thumbnail"]`, 0)
            .elementCountIs(`[data-file-id="${id}"] svg.lucide-file`, 1);
    }
    checkConditions(await dom.matches());
}

// ── Thumbnails: install a /gen-thumbs request capture (util) ────────────────
// useThumbnailGenerator (called both on TileViewOne mount AND after an upload
// from UploadMenu.jsx:94) POSTs /gen-thumbs in reversed batches of BATCH_SIZE=15,
// self-chaining the remainder in onSuccess. This records each batch's id-count +
// path so the batching pattern and the navigation guard can be asserted.
// Idempotent; returns the shared capture array.
export function installThumbCapture(page) {
    if (!page.__thumbReqs) {
        page.__thumbReqs = [];
        page.on("request", (r) => {
            if (!r.url().includes("/gen-thumbs")) return;
            let ids = null, path = null;
            try { const b = JSON.parse(r.postData()); ids = b.ids?.length ?? null; path = b.path ?? null; } catch {}
            page.__thumbReqs.push({ ids, path, ts: Date.now() });
        });
    }
    return page.__thumbReqs;
}

// ── Thumbnails: upload >15 images and assert the batch pattern (aspect R) ────
// The upload (one filechooser action) is what fires useThumbnailGenerator, so it
// owns the checks. Asserts every uploaded row landed (list markup) AND that
// /gen-thumbs fired in the expected reversed, self-chaining batches — e.g. 18
// images → [15, 3] — all carrying THIS folder's path. A broken batcher (wrong
// size, no self-chain, or a stalled chain that never emits the remainder) fails
// the batch-pattern check.
export async function uploadImagesObserveBatches(page, { files, folderPath, expectedBatchSizes }) {
    const testFiles = new TestFiles();
    for (const f of files) testFiles.addImage(f.name);
    const names = files.map((f) => f.name);
    const caps = installThumbCapture(page);
    const start = caps.length;
    const rowsBefore = await page.locator("tr span.truncate").count();

    try {
        const [fileChooser] = await Promise.all([
            page.waitForEvent("filechooser"),
            click(page, 'button[role="menuitem"]:text-is("Upload File")'),
        ]);
        await fileChooser.setFiles(testFiles.paths);

        const uploadReq = await networkCheck(page)
            .methodIs("POST").urlContains("/upload").httpCodeIs(302)
            .waitFor({ timeout: 30000 });
        await page.waitForLoadState("networkidle");
        for (const name of names) {
            await page.locator(`tr:has(span.truncate:text-is("${name}"))`).waitFor({ timeout: 20000 });
        }

        // Poll until the expected number of batches for THIS folder have fired.
        const deadline = Date.now() + 25000;
        const mine = () => caps.slice(start).filter((c) => c.path === folderPath);
        while (mine().length < expectedBatchSizes.length && Date.now() < deadline) {
            await page.waitForTimeout(300);
        }
        const batches = mine().map((c) => c.ids);

        let dom = domCheck(page).elementCountIs("tr span.truncate", rowsBefore + names.length);
        for (const name of names) dom = dom.elementCountIs(`span.truncate:text-is("${name}")`, 1);
        const rowsDom = await dom.matches();

        const batchChecks = [
            {
                name: "gen-thumbs batch count",
                passed: batches.length === expectedBatchSizes.length,
                expected: expectedBatchSizes.length,
                actual: batches.length,
            },
            {
                name: "gen-thumbs batch sizes (reversed, self-chaining)",
                passed: JSON.stringify(batches) === JSON.stringify(expectedBatchSizes),
                expected: expectedBatchSizes,
                actual: batches,
            },
        ];
        checkConditions(uploadReq, rowsDom, customResult(page, "thumb-batch", batchChecks));
    } finally {
        testFiles.cleanup();
    }
}

// ── Thumbnails: upload >15 images but HOLD the first batch (aspect R guard) ──
// Delays every /gen-thumbs response by `delayMs` so batch1 is still in flight
// when the caller navigates away next. Asserts the rows landed and that batch1
// was issued for this folder. Leaves the route + capture in place for
// leaveFolderAssertThumbHalt to finish and assert on. Route is removed there.
export async function uploadImagesHoldFirstBatch(page, { files, folderPath, delayMs = 3000 }) {
    const testFiles = new TestFiles();
    for (const f of files) testFiles.addImage(f.name);
    const names = files.map((f) => f.name);
    const caps = installThumbCapture(page);
    const start = caps.length;
    const rowsBefore = await page.locator("tr span.truncate").count();
    await page.route("**/gen-thumbs", async (route) => {
        await new Promise((r) => setTimeout(r, delayMs));
        await route.continue().catch(() => {});
    });

    try {
        const [fileChooser] = await Promise.all([
            page.waitForEvent("filechooser"),
            click(page, 'button[role="menuitem"]:text-is("Upload File")'),
        ]);
        await fileChooser.setFiles(testFiles.paths);

        const uploadReq = await networkCheck(page)
            .methodIs("POST").urlContains("/upload").httpCodeIs(302)
            .waitFor({ timeout: 30000 });
        await page.waitForLoadState("networkidle");
        for (const name of names) {
            await page.locator(`tr:has(span.truncate:text-is("${name}"))`).waitFor({ timeout: 20000 });
        }
        // batch1 is issued at upload; wait for the request itself (held response).
        const deadline = Date.now() + 8000;
        const mine = () => caps.slice(start).filter((c) => c.path === folderPath);
        while (mine().length < 1 && Date.now() < deadline) await page.waitForTimeout(200);

        let dom = domCheck(page).elementCountIs("tr span.truncate", rowsBefore + names.length);
        const rowsDom = await dom.matches();
        const batch1 = {
            name: "gen-thumbs first batch issued (held)",
            passed: mine().length >= 1 && mine()[0].ids === 15,
            expected: 15,
            actual: mine()[0]?.ids ?? null,
        };
        page.__thumbHoldStart = start;
        page.__thumbHoldFolder = folderPath;
        checkConditions(uploadReq, rowsDom, customResult(page, "thumb-hold", [batch1]));
    } finally {
        testFiles.cleanup();
    }
}

// ── Thumbnails: navigate away mid-batch, assert the chain HALTS (aspect R) ──
// Precondition: uploadImagesHoldFirstBatch left batch1 in flight for
// `leftFolderPath`. The action is an Inertia breadcrumb visit to the drive root
// (real client-side navigation, the path the pathname guard exists for). After
// letting the held response resolve and any (broken) self-chain fire, the number
// of /gen-thumbs requests carrying `leftFolderPath` MUST stay 1 — batch2 (the
// reversed remainder) must NEVER be issued for the folder we left. The halt is
// enforced jointly by useThumbnailGenerator's `window.location.pathname !== path`
// guard (line 17) and Inertia's visit-cancellation; if EITHER fails the batcher
// keeps generating thumbnails for an abandoned folder → count 2 → RED finding.
export async function leaveFolderAssertThumbHalt(page, { leftFolderPath, settleMs = 6000 }) {
    const caps = page.__thumbReqs ?? [];
    const start = page.__thumbHoldStart ?? 0;
    await click(page, 'nav[aria-label="Breadcrumb"] a[href="/drive"]');

    const navReq = await networkCheck(page)
        .methodIs("GET").urlContains("/drive").httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.waitForURL(/\/drive$/, { timeout: 15000 });
    await page.waitForLoadState("networkidle");
    // Let the held batch1 response land + any self-chain get a chance to fire.
    await page.waitForTimeout(settleMs);
    await page.unroute("**/gen-thumbs").catch(() => {});

    const forLeft = caps.slice(start).filter((c) => c.path === leftFolderPath);
    const navDom = await domCheck(page)
        .pathnameIs("/drive")
        .elementCountIs('nav[aria-label="Breadcrumb"]', 0)
        .matches();
    const haltCheck = {
        name: "no further /gen-thumbs for the folder we left (batch chain halted)",
        passed: forLeft.length === 1,
        expected: 1,
        actual: forLeft.length,
        batches: forLeft.map((c) => c.ids),
    };
    checkConditions(navReq, navDom, customResult(page, "thumb-guard", [haltCheck]));
}

// ── Ambient state restore: set viewMode back and reload (cleanup util) ───────
// `mode` is the value captured at setup (may be null → remove the key so the app
// falls back to its own default). The reload is the action; asserts the intended
// toggle is active so the restore is proven, not assumed.
export async function restoreViewMode(page, { mode }) {
    await page.evaluate((m) => {
        if (m === null) localStorage.removeItem("viewMode");
        else localStorage.setItem("viewMode", m);
    }, mode);
    await reload(page);
    await page.waitForLoadState("networkidle");

    const activeLabel = mode === "TileViewOne" ? "Tile view" : "List view";
    const dom = await domCheck(page)
        .hasClass(`button[aria-label="${activeLabel}"]`, "bg-gray-900")
        .matches();
    checkConditions(dom);
}

// ════════════════════════════════════════════════════════════════════════════
// Media viewer bug-hunt helpers (aspects P + Q; test-viewers.mjs). Selectors from
// resources/js/Pages/Drive/Components/FileList/{MediaViewer,ImageViewer,HtmlViewer,
// PdfViewer,AudioPlayer,VideoPlayer,TxtViewer}.jsx and FileBrowserSection.jsx:
//   - viewer overlay: `.fixed.inset-0` (Modal.jsx:11, isOpen=isPreviewModalOpen)
//   - open a file: click the row's `div[data-file-id]` → handleFileClick (FileItem.jsx:29-30)
//   - per-type renderer (MediaViewer.jsx:153-181):
//       image → <img src="/fetch-file/{id}">        (ImageViewer.jsx:5-7)
//       html  → <iframe src="/fetch-file/{id}">      (HtmlViewer.jsx:5-7)
//       pdf   → react-pdf <canvas> + "Page N of M"   (PdfViewer.jsx:27,56)
//       audio → <audio><source src="/fetch-file/{id}" type="audio/mpeg"> + ◁◁/1m buttons (AudioPlayer.jsx:48-110)
//       video → <video><source src="/fetch-file/{id}" type="video/mp4">  (VideoPlayer.jsx:21-30)
//       text  → <pre>content</pre>  (.md → <div class="prose">) (TxtViewer.jsx:161-177)
//   - prev/next: window ArrowLeft/ArrowRight → prev/nextClick, index resolved by id
//     against previewAbleFiles.current (MediaViewer.jsx:35-86)
//   - autoplay: <video/audio autoPlay={localStorage videoAutoplay/audioAutoplay}>
//     (VideoPlayer.jsx:9,25 / AudioPlayer.jsx:10,52) — read once at mount (useLocalStorageBool)
//   - saved position: audio-position-{id} written on timeupdate, restored on mount,
//     both gated on localStorage audioSavePosition (AudioPlayer.jsx:11-32)

const VIEWER_SEL = ".fixed.inset-0";

// The renderer element each file_type mounts (used for presence + delta negatives).
const RENDERER = {
    image: `${VIEWER_SEL} img`,
    html: `${VIEWER_SEL} iframe`,
    pdf: `${VIEWER_SEL} canvas`,
    audio: `${VIEWER_SEL} audio`,
    video: `${VIEWER_SEL} video`,
    txt: `${VIEWER_SEL} pre`,
    md: `${VIEWER_SEL} div.prose`,
};

// Shared assertion: the OPEN viewer is showing exactly the expected file, with the
// right renderer, the right /fetch-file/{id} src (or decoded content), and the OTHER
// renderers ABSENT — so it fails on a wrong-type render or the wrong file, not just
// "a modal exists". Called by both openPreviewByType and navViewerArrow after their
// single interaction. `folderName` anchors the pathname (preview is not a navigation).
async function assertViewerShows(page, { type, id, content, marker, folderName }) {
    // Wait for this type's renderer to be in the DOM before asserting.
    await page.locator(RENDERER[type]).first().waitFor({ timeout: 15000 }).catch(() => {});
    // TxtViewer mounts its <pre>/<div.prose> immediately with an "Empty File"
    // placeholder, then fills it after an async axios fetch of /fetch-file/{id}
    // (TxtViewer.jsx:50-58,114-117). Wait for the real body before asserting content.
    if ((type === "txt" || type === "md") && content) {
        await page.locator(`${RENDERER[type]}:has-text("${content}")`).first().waitFor({ timeout: 10000 }).catch(() => {});
    }

    let dom = domCheck(page).elementCountIs(VIEWER_SEL, 1).pathnameContains(folderName);
    const metrics = [];

    if (type === "image") {
        dom = dom
            .elementCountIs(`${VIEWER_SEL} img`, 1)
            .hasAttribute(`${VIEWER_SEL} img`, "src", `/fetch-file/${id}`)
            .elementDoesNotExist(`${VIEWER_SEL} pre`)
            .elementDoesNotExist(`${VIEWER_SEL} iframe`)
            .elementDoesNotExist(`${VIEWER_SEL} audio`)
            .elementDoesNotExist(`${VIEWER_SEL} video`)
            .elementDoesNotExist(`${VIEWER_SEL} canvas`);
        // Real decode: a broken/wrong src leaves naturalWidth 0.
        await page.waitForFunction(
            (sel) => { const i = document.querySelector(sel); return i && i.complete && i.naturalWidth > 0; },
            `${VIEWER_SEL} img`, { timeout: 10000 },
        ).catch(() => {});
        const nw = await page.locator(`${VIEWER_SEL} img`).evaluate((i) => i.naturalWidth).catch(() => 0);
        metrics.push({ name: `image decoded (naturalWidth>0), id ${id}`, passed: nw > 0, expected: ">0", actual: nw });
    } else if (type === "html") {
        dom = dom
            .elementCountIs(`${VIEWER_SEL} iframe`, 1)
            .hasAttribute(`${VIEWER_SEL} iframe`, "src", `/fetch-file/${id}`)
            .elementDoesNotExist(`${VIEWER_SEL} pre`)
            .elementDoesNotExist(`${VIEWER_SEL} img`)
            .elementDoesNotExist(`${VIEWER_SEL} audio`)
            .elementDoesNotExist(`${VIEWER_SEL} video`);
        // NOTE: /fetch-file serves file_type "html" as Content-Disposition: attachment
        // (FileFetchController.php:70-78 excludes html from the inline set), so the
        // <iframe> cannot render the document inline — the browser treats it as a
        // download. We therefore assert the iframe MOUNTED with the right file's src
        // (proves HtmlViewer opened for this id), not inline body content.
    } else if (type === "pdf") {
        dom = dom
            .elementCountIs(`${VIEWER_SEL} canvas`, 1)
            .elementContains(VIEWER_SEL, "Page 1 of 1")
            .elementDoesNotExist(`${VIEWER_SEL} pre`)
            .elementDoesNotExist(`${VIEWER_SEL} iframe`)
            .elementDoesNotExist(`${VIEWER_SEL} audio`)
            .elementDoesNotExist(`${VIEWER_SEL} video`);
    } else if (type === "audio") {
        dom = dom
            .elementCountIs(`${VIEWER_SEL} audio`, 1)
            .hasAttribute(`${VIEWER_SEL} audio source`, "src", `/fetch-file/${id}`)
            .hasAttribute(`${VIEWER_SEL} audio source`, "type", "audio/mpeg")
            // AudioPlayer's rewind control (◁◁ / "1m") is unique to this viewer.
            .elementExists(`${VIEWER_SEL} button:has-text("1m")`)
            .elementDoesNotExist(`${VIEWER_SEL} pre`)
            .elementDoesNotExist(`${VIEWER_SEL} iframe`)
            .elementDoesNotExist(`${VIEWER_SEL} img`)
            .elementDoesNotExist(`${VIEWER_SEL} video`);
        // Resolvable src: the mp3 loads metadata (readyState>=1) in Chromium.
        await page.waitForFunction(
            (sel) => { const a = document.querySelector(sel); return a && a.readyState >= 1; },
            `${VIEWER_SEL} audio`, { timeout: 10000 },
        ).catch(() => {});
        const rs = await page.locator(`${VIEWER_SEL} audio`).evaluate((a) => a.readyState).catch(() => 0);
        metrics.push({ name: `audio src resolvable (readyState>=1), id ${id}`, passed: rs >= 1, expected: ">=1", actual: rs });
    } else if (type === "video") {
        dom = dom
            .elementCountIs(`${VIEWER_SEL} video`, 1)
            .hasAttribute(`${VIEWER_SEL} video source`, "src", `/fetch-file/${id}`)
            .hasAttribute(`${VIEWER_SEL} video source`, "type", "video/mp4")
            .elementDoesNotExist(`${VIEWER_SEL} pre`)
            .elementDoesNotExist(`${VIEWER_SEL} iframe`)
            .elementDoesNotExist(`${VIEWER_SEL} img`)
            .elementDoesNotExist(`${VIEWER_SEL} audio`);
        await page.waitForFunction(
            (sel) => { const v = document.querySelector(sel); return v && v.videoWidth > 0; },
            `${VIEWER_SEL} video`, { timeout: 12000 },
        ).catch(() => {});
        const vw = await page.locator(`${VIEWER_SEL} video`).evaluate((v) => v.videoWidth).catch(() => 0);
        metrics.push({ name: `video decoded (videoWidth>0), id ${id}`, passed: vw > 0, expected: ">0", actual: vw });
    } else if (type === "txt") {
        dom = dom
            .elementCountIs(`${VIEWER_SEL} pre`, 1)
            .elementContains(`${VIEWER_SEL} pre`, content)
            // .md would render <div class="prose"> instead — its absence proves the .txt branch.
            .elementDoesNotExist(`${VIEWER_SEL} div.prose`)
            .elementDoesNotExist(`${VIEWER_SEL} img`)
            .elementDoesNotExist(`${VIEWER_SEL} iframe`)
            .elementDoesNotExist(`${VIEWER_SEL} audio`)
            .elementDoesNotExist(`${VIEWER_SEL} video`);
    } else if (type === "md") {
        dom = dom
            .elementCountIs(`${VIEWER_SEL} div.prose`, 1)
            .elementContains(`${VIEWER_SEL} div.prose`, content)
            // .txt would render a <pre> — its absence proves the markdown branch ran.
            .elementDoesNotExist(`${VIEWER_SEL} pre`)
            .elementDoesNotExist(`${VIEWER_SEL} iframe`)
            .elementDoesNotExist(`${VIEWER_SEL} audio`)
            .elementDoesNotExist(`${VIEWER_SEL} video`);
    }

    const domResult = await dom.matches();
    if (metrics.length) {
        checkConditions(domResult, customResult(page, "media-metric", metrics));
    } else {
        checkConditions(domResult);
    }
}

// ── Open a file's preview and assert the correct per-type viewer rendered ────
// One click (the row's data-file-id div) → MediaViewer mounts the type's sub-viewer.
// `type` ∈ image|html|pdf|audio|video|txt|md. Optional mount-triggered media checks:
//   expectAutoplay (bool|null) — the <video>/<audio> autoplay property must reflect the
//     localStorage toggle read at mount; restoreNear/restored — AudioPlayer must (or must
//     NOT) have restored the saved position for THIS id.
export async function openPreviewByType(page, opts) {
    const { filename, type, id, folderName, content, marker, expectAutoplay = null, restoreNear = null, restored = null } = opts;
    await click(page, `tr:has(span.truncate:text-is("${filename}")) div[data-file-id]`);
    await assertViewerShows(page, { type, id, content, marker, folderName });

    if (expectAutoplay !== null) {
        const mediaSel = type === "video" ? `${VIEWER_SEL} video` : `${VIEWER_SEL} audio`;
        const autoplayDom = await domCheck(page).propertyIs(mediaSel, "autoplay", expectAutoplay).matches();
        checkConditions(autoplayDom);
    }
    if (restoreNear !== null) {
        const audioSel = `${VIEWER_SEL} audio`;
        if (restored) {
            await page.waitForFunction(
                (arg) => { const a = document.querySelector(arg.sel); return a && Math.abs(a.currentTime - arg.near) < 0.6; },
                { sel: audioSel, near: restoreNear }, { timeout: 8000 },
            ).catch(() => {});
        } else {
            await page.waitForTimeout(1200);
        }
        const ct = await page.locator(audioSel).evaluate((a) => a.currentTime).catch(() => -1);
        const passed = restored ? Math.abs(ct - restoreNear) < 0.6 : ct < 0.6;
        const label = restored
            ? `audio position restored near ${restoreNear} for id ${id}`
            : `audio position NOT restored (save toggle off) for id ${id}`;
        checkConditions(customResult(page, "audio-restore", [{ name: label, passed, expected: restored ? `~${restoreNear}` : "~0", actual: ct }]));
    }
}

// ── Navigate the viewer with ← / → and assert it landed on the expected file ──
// window ArrowRight→nextClick / ArrowLeft→prevClick; the target file is resolved by
// id against previewAbleFiles.current (MediaViewer.jsx:35-70). Asserts the viewer now
// shows the expected file (right renderer + right /fetch-file/{id} src or content). At
// a list end the arrow is a no-op (prev/next id is null) — pass the CURRENT file as
// `expected` to assert the body did not wrap. No wrapper covers a key press → scope by hand.
export async function navViewerArrow(page, { dir, type, id, content, marker, folderName }) {
    setLastActionTime(page);
    await page.keyboard.press(dir === "next" ? "ArrowRight" : "ArrowLeft");
    await assertViewerShows(page, { type, id, content, marker, folderName });
}

// ── Close the preview (Escape) from a drive-folder listing ──────────────────
// Escape → onCloseModal → setIsModalOpen(false): the Modal renders null so the overlay
// and viewer are gone. NOTE: MediaViewer stays MOUNTED because FileBrowserSection only
// gates it on `previewFile` (FileBrowserSection.jsx:467), never cleared on close — the
// retained-state root cause behind the stale-index hunt below. Asserts the overlay is
// gone and we are still in the folder (closing is not a navigation).
export async function closeViewer(page, { folderName, siblingFilename }) {
    setLastActionTime(page);
    await page.keyboard.press("Escape");
    await page.locator(VIEWER_SEL).waitFor({ state: "detached", timeout: 6000 }).catch(() => {});

    const dom = await domCheck(page)
        .elementCountIs(VIEWER_SEL, 0)
        .elementCountIs(`tr:has(span.truncate:text-is("${siblingFilename}"))`, 1)
        .pathnameContains(folderName)
        .matches();
    checkConditions(dom);
}

// ── Sort the file list by a column header (drive list) ───────────────────────
// Clicking a header th (ListView.jsx:51-86 → handleSortClick → sortCol) re-sorts
// filesCopy and re-renders the rows in the new order. The bug this exists to catch:
// the viewer's prev/next order (previewAbleFiles.current) is built ONCE at load and
// NOT rebuilt on re-sort (FileBrowserSection.jsx:285-307, effect keyed on [files]
// only), so after a re-sort the arrows still walk the ORDER AT LOAD. Asserts the
// DISPLAYED order actually changed (delta) and the clicked column is now the active
// sort (text-blue-400); the nav phase then proves the arrows follow THIS new order.
export async function sortByColumn(page, { columnLabel, previewableNames }) {
    const readPreviewOrder = async () => {
        const names = await page.evaluate(() =>
            [...document.querySelectorAll("div[data-file-id]")].map((el) =>
                el.querySelector("span.truncate")?.textContent.trim()),
        );
        return names.filter((n) => previewableNames.includes(n));
    };
    const headerSel = `th:has(span:text-is("${columnLabel}"))`;
    const before = await readPreviewOrder();
    await click(page, headerSel);
    await page.waitForLoadState("networkidle").catch(() => {});
    const after = await readPreviewOrder();
    const changed = JSON.stringify(before) !== JSON.stringify(after);
    const dom = await domCheck(page).hasClass(headerSel, "text-blue-400").matches();
    checkConditions(dom, customResult(page, "sort-column", [
        { name: `re-sort by ${columnLabel} changed the displayed order`, passed: changed, expected: "order != before", actual: JSON.stringify({ before, after }) },
    ]));
}

// ── Seed the playback localStorage toggles (ambient state; util) ────────────
// useLocalStorageBool reads the key at MOUNT (useLocalStorageBool.jsx:4-7), and each
// viewer remounts when the Modal reopens, so setting the key before an open is enough
// (no reload). Values are JSON booleans. Reads them back so a mis-seed fails loudly.
export async function setPlaybackToggles(page, toggles) {
    setLastActionTime(page);
    await page.evaluate((t) => {
        for (const [k, v] of Object.entries(t)) localStorage.setItem(k, JSON.stringify(v));
    }, toggles);
    const readBack = await page.evaluate((keys) => {
        const out = {};
        for (const k of keys) out[k] = localStorage.getItem(k);
        return out;
    }, Object.keys(toggles));
    const checks = Object.entries(toggles).map(([k, v]) => ({
        name: `localStorage ${k} = ${v}`,
        passed: readBack[k] === JSON.stringify(v),
        expected: JSON.stringify(v),
        actual: readBack[k],
    }));
    checkConditions(customResult(page, "playback-toggles", checks));
}

// ── Drive an open audio to a position so timeupdate persists audio-position-{id} ──
// Setting currentTime on the loaded <audio> and dispatching the real 'timeupdate'
// event fires AudioPlayer's saveTime listener (AudioPlayer.jsx:23-27), which writes
// localStorage audio-position-{id}. Deterministic regardless of autoplay policy.
// Asserts the per-id key was written near the seek target (a shared/global key would
// land under the wrong name and fail this).
export async function driveAudioPosition(page, { id, atSeconds }) {
    const audioSel = `${VIEWER_SEL} audio`;
    setLastActionTime(page);
    await page.waitForFunction(
        (sel) => { const a = document.querySelector(sel); return a && a.readyState >= 1; },
        audioSel, { timeout: 10000 },
    );
    await page.locator(audioSel).evaluate((a, t) => {
        a.currentTime = t;
        a.dispatchEvent(new Event("timeupdate"));
    }, atSeconds);
    await page.waitForTimeout(250);

    const saved = await page.evaluate((fid) => localStorage.getItem(`audio-position-${fid}`), id);
    const num = Number(saved);
    const passed = saved !== null && Math.abs(num - atSeconds) < 0.6;
    const dom = await domCheck(page).elementCountIs(audioSel, 1).matches();
    checkConditions(dom, customResult(page, "audio-position-write", [
        { name: `audio-position-${id} written near ${atSeconds}`, passed, expected: `~${atSeconds}`, actual: saved },
    ]));
}

// ── Assert a per-id localStorage position key exists / is absent (util) ─────
// Proves the saved-position key is namespaced by file id: aud1's key holds its value
// while aud2 has none (no shared/global "audio-position"). Not an interaction — a
// direct localStorage read wrapped as a failable check.
export async function assertAudioPositionKeys(page, { present = [], absent = [] }) {
    setLastActionTime(page);
    const state = await page.evaluate((ids) => {
        const out = {};
        for (const id of ids) out[id] = localStorage.getItem(`audio-position-${id}`);
        // Also confirm no un-namespaced global key leaked.
        out.__global = localStorage.getItem("audio-position-");
        return out;
    }, [...present.map((p) => p.id), ...absent]);
    const checks = [];
    for (const p of present) {
        const v = Number(state[p.id]);
        checks.push({
            name: `audio-position-${p.id} present near ${p.near}`,
            passed: state[p.id] !== null && Math.abs(v - p.near) < 0.6,
            expected: `~${p.near}`, actual: state[p.id],
        });
    }
    for (const id of absent) {
        checks.push({ name: `audio-position-${id} absent (per-id, not shared)`, passed: state[id] === null, expected: null, actual: state[id] });
    }
    checks.push({ name: "no un-namespaced audio-position- key", passed: state.__global === null, expected: null, actual: state.__global });
    checkConditions(customResult(page, "audio-position-keys", checks));
}

// ── Stale-index hunt: an arrow key after the previewed file was deleted ──────
// High-value aspect-P bug class. The viewer was opened on file X then Escaped: the
// modal is closed but MediaViewer stays mounted with its window keydown listener still
// attached (effect deps [isModalOpen] only, re-adds regardless — MediaViewer.jsx:88-99),
// carrying a stale currentFileIndex. X is then deleted, shrinking previewAbleFiles.current.
// Pressing the arrow runs the stale nextClick/prevClick: previewAbleFiles.current[stale]
// is now undefined and `undefined["next"]` throws (MediaViewer.jsx:57,67).
// CORRECT contract asserted here: no browser crash, the SPA/list stays intact, the viewer
// does not resurrect showing an undefined/wrong file. A TypeError → assertNoErrors → RED
// is the finding. `dir` picks the arrow; `siblingFilename` is a row that must survive.
export async function pressArrowExpectNoStaleCrash(page, { dir, folderName, siblingFilename }) {
    setLastActionTime(page);
    await page.keyboard.press(dir === "next" ? "ArrowRight" : "ArrowLeft");
    await page.waitForTimeout(600);

    const dom = await domCheck(page)
        // The modal was closed and must stay closed (no undefined file resurrected into view).
        .elementCountIs(VIEWER_SEL, 0)
        // The list is still rendered — a crash that unmounts the tree would drop these.
        .elementCountIs(`tr:has(span.truncate:text-is("${siblingFilename}"))`, 1)
        .pathnameContains(folderName)
        .matches();
    // checkConditions also runs assertNoErrors(page): the stale-index TypeError surfaces
    // as a pageerror here and turns this step RED — that is the reported finding.
    checkConditions(dom);
}

// ── Cleanup: clear the viewer localStorage this run set, verify by absence ───
// Removes the autoplay/save toggles, the per-id audio-position keys, and the txt
// edit hint, then reads them back — a leftover key would change the next run's
// viewer behaviour (ambient state), so absence is asserted, not assumed.
export async function clearViewerLocalStorage(page, { audioIds = [] }) {
    setLastActionTime(page);
    await page.evaluate((ids) => {
        for (const k of ["audioAutoplay", "videoAutoplay", "audioSavePosition", "txt_edit_hint"]) {
            localStorage.removeItem(k);
        }
        for (const id of ids) localStorage.removeItem(`audio-position-${id}`);
    }, audioIds);
    const remaining = await page.evaluate((ids) => {
        const keys = ["audioAutoplay", "videoAutoplay", "audioSavePosition", "txt_edit_hint", ...ids.map((id) => `audio-position-${id}`)];
        return keys.filter((k) => localStorage.getItem(k) !== null);
    }, audioIds);
    const checks = [{ name: "viewer localStorage keys cleared", passed: remaining.length === 0, expected: "[]", actual: JSON.stringify(remaining) }];
    checkConditions(customResult(page, "ls-clear", checks));
}

// ═══════════════════════════════════════════════════════════════════════════
// ADMIN SETTINGS (/admin-config) — config update + persistence, API tokens.
// SOURCE-ONLY discovery. Endpoints/flash from routes/web.php, AdminConfigController,
// ApiTokenController, FlashMessages, HandleInertiaMiddleware, CommonRequest:
//   GET    /admin-config        → Inertia Settings (200); tabs Config/REST API/Documentation.
//   POST   /admin-config/update → validate storage_path (pathRules: nullable|string|
//                                 SAFE_CHARS regex|no `..` traversal|max:512), then
//                                 updateStoragePath() → successTo('drive', 'Storage path
//                                 updated successfully') = 302 → /drive + flash status:true;
//                                 on failure error() = 302 back, status:false (bg-error).
//   POST   /api-tokens          → success('Token created', {plain_text_token}) 302 back;
//                                 React shows the plaintext ONCE in a modal (flash effect).
//   DELETE /api-tokens/{id}     → success('Token deleted') / error('Token not found') 302 back.
// The ONLY server-persisted config field is storage_path (a text input). The "Media
// Settings" ToggleRows (videoAutoplay/audioAutoplay/audioSavePosition) are localStorage-
// only (useLocalStorageToggle — never POSTed), so they cannot exercise the update endpoint.
// Changing storage_path is destructive (applyStoragePath: LocalFile::clearTable() + rescan
// + shares reset), so tests move it to INVISIBLE sibling dirs of CONTENT_SUBDIR
// ('storage_personaldrive') and RESTORE the original in cleanup. Two-factor is untouched.

// ── Admin: navigate to /admin-config, assert the page + its tabs render ───────
export async function gotoAdminConfig(page, { base }) {
    await goto(page, base + "/admin-config");
    const req = await networkCheck(page)
        .methodIs("GET")
        .urlContains("/admin-config")
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");
    const dom = await domCheck(page)
        .pathnameIs("/admin-config")
        // Three tabs (Settings.jsx TABS). Their absence = the admin page did not render.
        .elementCountIs('button:text-is("Config")', 1)
        .elementCountIs('button:text-is("REST API")', 1)
        .elementCountIs('button:text-is("Documentation")', 1)
        // Config is the default tab → the only server-persisted field is present.
        .elementCountIs("#storage_path", 1)
        .matches();
    checkConditions(req, dom);
}

// ── Admin: read the current storage_path value (a read, not an action) ────────
export async function readStoragePath(page) {
    return await page.locator("#storage_path").inputValue();
}

// ── Admin: read the number of existing API-token rows (a read) ────────────────
export async function readTokenRowCount(page) {
    return await page.locator("table tbody tr").count();
}

// ── Admin: fill the storage_path field ───────────────────────────────────────
export async function fillStoragePath(page, { value }) {
    await fill(page, "#storage_path", value);
    const dom = await domCheck(page)
        .inputValueIs("#storage_path", value)
        .elementCountIs('button:has-text("Update Settings"):visible', 1)
        .matches();
    checkConditions(dom);
}

// ── Admin: submit the config form expecting SUCCESS + persistence redirect ────
// Contract: 302 POST → controller successTo('drive') lands us on /drive with the
// bg-success flash "Storage path updated successfully". A change that silently no-ops
// (no redirect / bg-error) fails here; persistence itself is proven by the follow-up
// verifyStoragePathPersisted (a reloaded GET showing the NEW value, not the old).
export async function submitConfigUpdate(page, { newValue }) {
    await click(page, 'button:has-text("Update Settings"):visible');
    const req = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/admin-config/update")
        .httpCodeIs(302)
        .payloadContains("storage_path")
        .waitFor({ timeout: 20000 });
    await page.waitForURL(/\/drive/, { timeout: 20000 });
    await page.waitForLoadState("networkidle");
    const dom = await domCheck(page)
        .pathnameIs("/drive")
        .elementExists(".bg-success")
        .htmlContains("Storage path updated successfully")
        .htmlDoesNotContain("Unable to update storage path")
        .matches();
    checkConditions(req, dom);
}

// ── Admin: reload /admin-config and assert storage_path PERSISTED the change ──
// The delta+persistence check: after a real update, a fresh GET must render the NEW
// value (Setting::getStoragePath from the DB). `notExpected` guards against a stale
// value; a change that did not persist shows the old value here → RED.
export async function verifyStoragePathPersisted(page, { base, expected, notExpected = null }) {
    await goto(page, base + "/admin-config");
    const req = await networkCheck(page)
        .methodIs("GET")
        .urlContains("/admin-config")
        .httpCodeIs(200)
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");
    let dom = domCheck(page)
        .pathnameIs("/admin-config")
        .inputValueIs("#storage_path", expected);
    if (notExpected !== null) dom = dom.inputValueIsNot("#storage_path", notExpected);
    checkConditions(req, await dom.matches());
}

// ── Admin: submit config with a MALFORMED storage_path expecting REJECTION ────
// Precondition: fillStoragePath has set an invalid value (a filesystem-legal path
// carrying a char pathRules' SAFE_CHARS regex forbids, e.g. `*`). Contract: the server
// refuses it — NO redirect to /drive, NO success flash. If validation accepts it (the
// bug), the controller updates + redirects to /drive → these assertions fail RED. The
// DB-unchanged half is proven by a follow-up verifyStoragePathPersisted(prev value).
export async function submitConfigUpdateExpectRejected(page, { badValue }) {
    await click(page, 'button:has-text("Update Settings"):visible');
    const req = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/admin-config/update")
        .waitFor({ timeout: 15000 });
    await page.waitForLoadState("networkidle");
    await page.waitForTimeout(700);
    const dom = await domCheck(page)
        .pathnameDoesNotContain("/drive")
        .htmlDoesNotContain("Storage path updated successfully")
        .matches();
    checkConditions(req, dom);
}

// ── Admin: switch to the REST API (tokens) tab (client-only setActiveTab) ─────
// Optional present/absent/count assert the token table across a reload (persistence).
export async function switchToTokensTab(page, { present = [], absent = [], count = null } = {}) {
    await click(page, 'button:text-is("REST API")');
    await page.waitForLoadState("networkidle").catch(() => {});
    await page.locator('button[type="submit"]:has-text("Create")').first().waitFor({ timeout: 5000 }).catch(() => {});
    let dom = domCheck(page)
        .elementCountIs('button[type="submit"]:has-text("Create")', 1)
        .elementExists('h2:text-is("Create Token")')
        .elementExists('h2:text-is("Existing Tokens")')
        // The config field is gone → this is really the tokens tab, not the config tab.
        .elementDoesNotExist("#storage_path");
    if (count !== null) dom = dom.elementCountIs("table tbody tr", count);
    for (const name of present) dom = dom.elementCountIs(`tr:has(td:text-is("${name}"))`, 1);
    for (const name of absent) dom = dom.elementDoesNotExist(`tr:has(td:text-is("${name}"))`);
    checkConditions(await dom.matches());
}

// ── Admin: fill the new-token name field ─────────────────────────────────────
export async function fillTokenName(page, { name }) {
    await fill(page, 'input[placeholder^="Token name"]', name);
    const dom = await domCheck(page)
        .inputValueIs('input[placeholder^="Token name"]', name)
        .matches();
    checkConditions(dom);
}

// ── Admin: create a token — assert the creation delta (row +1, name present) ──
// The token-issue contract that MUST hold: POST 302 and the row total grows by exactly
// one with a row bearing this name (a create that no-ops or dupes fails here). The
// one-time plaintext DISPLAY is checked separately (createTokenAssertPlaintext) because
// it is broken in this build — see that function. Defensively closes the "Token Created"
// modal if a (fixed) build shows it, so consecutive creates are not blocked by an overlay.
export async function createToken(page, { name, prevCount }) {
    await click(page, 'button[type="submit"]:has-text("Create")');
    const req = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/api-tokens")
        .httpCodeIs(302)
        .waitFor({ timeout: 20000 });
    await page.waitForLoadState("networkidle");
    await page.locator(`tr:has(td:text-is("${name}"))`).first().waitFor({ timeout: 8000 }).catch(() => {});
    if ((await page.locator('h3:text-is("Token Created")').count()) === 1) {
        await click(page, 'button:has-text("Close")');
        await page.locator('h3:text-is("Token Created")').waitFor({ state: "detached", timeout: 5000 }).catch(() => {});
    }
    const dom = await domCheck(page)
        .elementCountIs(`tr:has(td:text-is("${name}"))`, 1)
        .elementCountIs("table tbody tr", prevCount + 1)
        .matches();
    checkConditions(req, dom);
}

// ── Admin: the one-time plaintext token display ───────────────────────────────
// Contract: creating a token MUST show its secret plaintext exactly once. The token
// is created (row +1) AND the "Token Created" modal now opens with the secret
// (ApiTokensTab.jsx:13,29 read flash.more_info.plain_text_token — the key-mismatch
// bug was fixed). The secret div (ApiTokensTab.jsx:175) must be selected scoped to the
// modal: ApiDocs on the same tab also renders `.font-mono.break-all` (endpoint URLs),
// so an unscoped selector grabs `/api/v1/…` instead of the token.
export async function createTokenAssertPlaintext(page, { name, prevCount }) {
    await click(page, 'button[type="submit"]:has-text("Create")');
    const req = await networkCheck(page)
        .methodIs("POST")
        .urlContains("/api-tokens")
        .httpCodeIs(302)
        .waitFor({ timeout: 20000 });
    await page.waitForLoadState("networkidle");
    await page.locator(`tr:has(td:text-is("${name}"))`).first().waitFor({ timeout: 8000 }).catch(() => {});
    // Server-side creation succeeds …
    const created = await domCheck(page)
        .elementCountIs(`tr:has(td:text-is("${name}"))`, 1)
        .elementCountIs("table tbody tr", prevCount + 1)
        .matches();
    // … but the one-time plaintext modal must appear with the secret shown once.
    const modalShown = (await page.locator('h3:text-is("Token Created")').count()) === 1;
    const modalSel = 'div.fixed.inset-0.z-50:has(h3:text-is("Token Created"))';
    const tokenText = modalShown ? ((await page.locator(`${modalSel} .font-mono.break-all`).first().textContent()) ?? "").trim() : "";
    const sanctumOk = /^\d+\|[A-Za-z0-9]{40,}$/.test(tokenText);
    checkConditions(req, created, customResult(page, "token-plaintext-once", [
        { name: "one-time 'Token Created' modal shown after create", passed: modalShown, expected: true, actual: modalShown },
        { name: "plaintext secret displayed exactly once (sanctum {id}|{hash})", passed: modalShown && sanctumOk, expected: "\\d+|<hash>", actual: modalShown ? tokenText.slice(0, 8) + "…" : "(no modal — secret unrecoverable)" },
    ]));
}

// ── Admin: revoke ONE token by name — scope control (the wrong-id bug class) ──
// Deletes via the per-row button (handleDelete → DELETE /api-tokens/{id}, confirm()
// dialog). Contract: exactly the named row disappears, the `survivor` row stays, and
// the total drops by one. Revoking the wrong id would drop the survivor instead → RED.
export async function revokeTokenByName(page, { name, survivor, prevCount }) {
    const rowSel = `tr:has(td:text-is("${name}"))`;
    page.once("dialog", (d) => d.accept());
    await click(page, `${rowSel} button:has-text("Delete")`);
    const req = await networkCheck(page)
        .methodIs("DELETE")
        .urlContains("/api-tokens/")
        .httpCodeIs(303)
        .waitFor({ timeout: 20000 });
    await page.waitForLoadState("networkidle");
    await page.locator(rowSel).first().waitFor({ state: "detached", timeout: 5000 }).catch(() => {});
    const dom = await domCheck(page)
        .elementDoesNotExist(rowSel)
        .elementCountIs(`tr:has(td:text-is("${survivor}"))`, 1)
        .elementCountIs("table tbody tr", prevCount - 1)
        .matches();
    checkConditions(req, dom);
}

// ── Admin: cleanup-tolerant revoke — only if the row still exists ─────────────
// Used in finally so a token already revoked in the main flow does not throw and mask
// the run's own failure; still asserts real absence after a revoke it actually issues.
export async function revokeTokenIfPresent(page, { name }) {
    const rowSel = `tr:has(td:text-is("${name}"))`;
    if ((await page.locator(rowSel).count()) === 0) return;
    page.once("dialog", (d) => d.accept());
    await click(page, `${rowSel} button:has-text("Delete")`);
    const req = await networkCheck(page)
        .methodIs("DELETE")
        .urlContains("/api-tokens/")
        .httpCodeIs(303)
        .waitFor({ timeout: 20000 });
    await page.waitForLoadState("networkidle");
    await page.locator(rowSel).first().waitFor({ state: "detached", timeout: 5000 }).catch(() => {});
    const dom = await domCheck(page).elementDoesNotExist(rowSel).matches();
    checkConditions(req, dom);
}
