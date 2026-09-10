<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\BaseFeatureTest;

/**
 * The listing endpoints must describe files that actually exist on disk.
 *
 * Invariant under test: a LocalFile row with no file on disk must not appear in
 * a listing, must not be counted in `total`, and must not create a page gap.
 *
 * Rows can go stale out-of-band (SFTP, rsync, permissions) or in-band
 * (partial-failure move/delete leaving the row behind).
 */
class DiskTruthListingTest extends BaseFeatureTest
{
    use RefreshDatabase;

    private User $apiUser;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();

        $this->apiUser = User::first();
        $this->token = $this->apiUser->createToken('test-api-token', ['api'])->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    /** @return array{files: array, meta: array, links: array} */
    private function page(int $page = 1, int $perPage = 2): array
    {
        $response = $this->getJson(
            "/api/v1/files?per_page={$perPage}&page={$page}",
            $this->authHeaders()
        );
        $response->assertOk();

        return $response->json();
    }

    private function uploadNames(array $names): void
    {
        foreach ($names as $name) {
            $this->uploadFile('', $name, 10);
        }
    }

    private function removeFromDisk(array $names): void
    {
        foreach ($names as $name) {
            Storage::disk('local')->delete(CONTENT_SUBDIR . DS . $name);
        }
    }

    /**
     * Walk pages the way a real client does, and report what it saw.
     *
     * @return array{pages: array<int, array<string>>, names: array<string>, meta: array}
     */
    private function walkAllPages(int $perPage = 2): array
    {
        $pages = [];
        $names = [];
        $page = 1;
        $meta = [];

        while ($page <= 50) {
            $body = $this->page($page, $perPage);
            $meta = $body['meta'];
            $pages[$page] = array_column($body['files'], 'filename');
            $names = array_merge($names, $pages[$page]);

            if ($page >= $meta['last_page']) {
                break;
            }
            $page++;
        }

        return ['pages' => $pages, 'names' => $names, 'meta' => $meta];
    }

    public function test_listing_excludes_rows_whose_files_are_missing_from_disk(): void
    {
        $this->uploadNames(['a.txt', 'b.txt', 'c.txt', 'd.txt', 'e.txt']);
        $this->removeFromDisk(['a.txt', 'b.txt', 'c.txt']);

        $body = $this->page(1, 2);

        $this->assertSame(
            ['e.txt', 'd.txt'],
            array_column($body['files'], 'filename'),
            'Listing should contain only files present on disk'
        );
        $this->assertSame(2, $body['meta']['total'], 'total must count files that exist on disk');
        $this->assertSame(1, $body['meta']['last_page'], 'last_page must not describe phantom rows');
    }

    public function test_listing_pages_are_contiguous_when_a_middle_page_empties(): void
    {
        $this->uploadNames(['a.txt', 'b.txt', 'c.txt', 'd.txt', 'e.txt', 'f.txt']);
        // c/d sit on page 2 of 3 (filename desc), so page 2 empties out.
        $this->removeFromDisk(['c.txt', 'd.txt']);

        $walk = $this->walkAllPages(2);

        $this->assertSame(
            ['f.txt', 'e.txt'],
            $walk['pages'][1],
            'page 1 should be the first two live files'
        );
        $this->assertSame(
            ['b.txt', 'a.txt'],
            $walk['pages'][2] ?? [],
            'page 2 should follow immediately, with no gap'
        );
        $this->assertSame(2, $walk['meta']['last_page'], 'live files fit in two pages');
        $this->assertSame(4, $walk['meta']['total']);
    }

    public function test_no_page_is_empty_while_later_files_remain(): void
    {
        $this->uploadNames(['a.txt', 'b.txt', 'c.txt', 'd.txt', 'e.txt', 'f.txt']);
        $this->removeFromDisk(['c.txt', 'd.txt']);

        $walk = $this->walkAllPages(2);

        foreach ($walk['pages'] as $number => $names) {
            $isLastPage = $number === count($walk['pages']);
            if (!$isLastPage) {
                $this->assertNotSame(
                    [],
                    $names,
                    "page {$number} is empty but later pages have files — a client following "
                    . 'links.next-by-items would silently stop here'
                );
            }
        }
    }

    public function test_every_file_on_disk_is_reachable_through_pagination(): void
    {
        $live = ['a.txt', 'b.txt', 'e.txt', 'f.txt'];
        $this->uploadNames(['a.txt', 'b.txt', 'c.txt', 'd.txt', 'e.txt', 'f.txt']);
        $this->removeFromDisk(['c.txt', 'd.txt']);

        $walk = $this->walkAllPages(2);
        $unreachable = array_values(array_diff($live, $walk['names']));

        $this->assertSame([], $unreachable, 'no file on disk may be unreachable via pagination');
        $this->assertSame(4, $walk['meta']['total']);
    }

    public function test_inertia_drive_listing_omits_files_missing_from_disk(): void
    {
        $this->uploadNames(['a.txt', 'b.txt', 'c.txt']);
        $this->removeFromDisk(['b.txt']);

        $response = $this->get(route('drive'));
        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Drive/DriveHome')
                ->where('files', fn ($files) => collect($files)
                    ->pluck('filename')
                    ->values()
                    ->all() === ['c.txt', 'a.txt'])
        );
    }
}
