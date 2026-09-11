<?php

return [
    'server_configs' => [
        [
            'name' => 'php-fpm',
            'instruction' => 'Edit the www.conf file',
            'code' => "php_value[upload_max_filesize] = 1G\nphp_value[post_max_size] = 1G\nphp_value[max_file_uploads] = 1000",
        ],
        [
            'name' => 'PHP',
            'instruction' => 'Edit 3 variables in php.ini file',
            'code' => "upload_max_filesize = 1G\npost_max_size = 1G\nmax_file_uploads = 10000",
        ],
        [
            'name' => 'apache',
            'instruction' => 'edit the .htaccess file in /public',
            'code' => "php_value upload_max_filesize 64M\nphp_value post_max_size 64M\nphp_value max_file_uploads 10000",
        ],
        [
            'name' => 'nginx',
            'instruction' => 'Increase client_max_body_size param',
            'code' => "http {\n    client_max_body_size 1000M;\n}",
        ],
        [
            'name' => 'Caddy',
            'instruction' => 'Increase request_timeout param',
            'code' => "demo.personaldrive.xyz {\n    root * /some/folder\n    php_fastcgi unix/{{ php_fpm_socket.stdout }}\n    file_server\n    request_body {\n        max_size 1G\n        timeout 1000s\n    }\n}",
        ],
    ],

    'api_sections' => [
        [
            'title' => 'Files',
            'description' => 'Manage files and folders in your drive.',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'url' => '/api/v1/files',
                    'title' => 'List Files',
                    'description' => 'List files and folders at a given path. Returns paginated results.',
                    'params' => [
                        ['name' => 'path', 'type' => 'string', 'required' => false, 'description' => 'Directory path to list. Root if omitted.'],
                        ['name' => 'per_page', 'type' => 'integer', 'required' => false, 'description' => 'Results per page (default: 50).'],
                    ],
                    'body' => null,
                    'response' => '{
  "files": [ File object, ... ],
  "links": { ... },
  "meta":  { ... },
  "path": "Documents"
}',
                    'curl' => 'curl -s "https://your-domain.com/api/v1/files?path=Documents&per_page=20" \\
  -H "Authorization: Bearer <your-token>"',
                ],
                [
                    'method' => 'GET',
                    'url' => '/api/v1/files/{id}',
                    'title' => 'Get File Info',
                    'description' => 'Get metadata for a single file or folder.',
                    'params' => [],
                    'body' => null,
                    'response' => '{
  "file": File object
}',
                    'curl' => 'curl -s "https://your-domain.com/api/v1/files/01HXYZ..." \\
  -H "Authorization: Bearer <your-token>"',
                ],
                [
                    'method' => 'POST',
                    'url' => '/api/v1/files/upload',
                    'title' => 'Upload Files',
                    'description' => 'Upload one or more files. Use multipart/form-data. Files with name conflicts are saved to a temp directory.',
                    'params' => [],
                    'body' => [
                        ['name' => 'files[]', 'type' => 'file[]', 'required' => true, 'description' => 'One or more files to upload.'],
                        ['name' => 'path', 'type' => 'string', 'required' => false, 'description' => 'Destination directory path. Root if omitted.'],
                    ],
                    'response' => '{
  "message": "Files uploaded: 2 out of 2",
  "files": [ File object, ... ]
}',
                    'curl' => 'curl -s -X POST "https://your-domain.com/api/v1/files/upload" \\
  -H "Authorization: Bearer <your-token>" \\
  -F "files[]=@/path/to/photo.jpg" \\
  -F "files[]=@/path/to/doc.pdf" \\
  -F "path=Documents"',
                ],
                [
                    'method' => 'POST',
                    'url' => '/api/v1/files/create',
                    'title' => 'Create File or Folder',
                    'description' => 'Create an empty file or a new folder.',
                    'params' => [],
                    'body' => [
                        ['name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'Name for the new file or folder.'],
                        ['name' => 'type', 'type' => 'string', 'required' => true, 'description' => '"file" or "folder".'],
                        ['name' => 'path', 'type' => 'string', 'required' => false, 'description' => 'Parent directory path. Root if omitted.'],
                    ],
                    'response' => '{
  "message": "Folder created",
  "file": File object
}',
                    'curl' => 'curl -s -X POST "https://your-domain.com/api/v1/files/create" \\
  -H "Authorization: Bearer <your-token>" \\
  -H "Content-Type: application/json" \\
  -d \'{"name": "New Folder", "type": "folder", "path": "Documents"}\'',
                ],
                [
                    'method' => 'GET',
                    'url' => '/api/v1/files/{id}/download',
                    'title' => 'Download File',
                    'description' => 'Download a file. Returns the raw file content with the correct Content-Type header.',
                    'params' => [],
                    'body' => null,
                    'response' => 'Raw file content (binary)',
                    'curl' => 'curl -s -o /tmp/downloaded.jpg "https://your-domain.com/api/v1/files/01HXYZ.../download" \\
  -H "Authorization: Bearer <your-token>"',
                ],
                [
                    'method' => 'DELETE',
                    'url' => '/api/v1/files/{id}',
                    'title' => 'Delete File',
                    'description' => 'Delete a file or folder and its contents.',
                    'params' => [],
                    'body' => null,
                    'response' => '{
  "message": "Files deleted",
  "deleted": true
}',
                    'curl' => 'curl -s -X DELETE "https://your-domain.com/api/v1/files/01HXYZ..." \\
  -H "Authorization: Bearer <your-token>"',
                ],
                [
                    'method' => 'POST',
                    'url' => '/api/v1/files/move',
                    'title' => 'Move Files',
                    'description' => 'Move one or more files/folders to a different directory. Paths are relative to root with no leading slash.',
                    'params' => [],
                    'body' => [
                        ['name' => 'fileList[]', 'type' => 'string[]', 'required' => true, 'description' => 'Array of file ULIDs to move.'],
                        ['name' => 'destination', 'type' => 'string', 'required' => true, 'description' => 'Relative path from root. "/" = root, "Documents" = root subfolder, "Documents/Archive" = nested.'],
                    ],
                    'response' => '{
  "message": "Files moved",
  "files": [ File object, ... ]
}',
                    'curl' => '# Move to root
curl -s -X POST "https://your-domain.com/api/v1/files/move" \\
  -H "Authorization: Bearer <your-token>" \\
  -H "Content-Type: application/json" \\
  -d \'{"fileList": ["01HXYZ..."], "destination": "/"}\'

# Move into a root-level folder
curl -s -X POST "https://your-domain.com/api/v1/files/move" \\
  -H "Authorization: Bearer <your-token>" \\
  -H "Content-Type: application/json" \\
  -d \'{"fileList": ["01HXYZ..."], "destination": "Documents"}\'

# Move into a nested subfolder
curl -s -X POST "https://your-domain.com/api/v1/files/move" \\
  -H "Authorization: Bearer <your-token>" \\
  -H "Content-Type: application/json" \\
  -d \'{"fileList": ["01HXYZ..."], "destination": "Documents/Archive"}\'',
                ],
                [
                    'method' => 'POST',
                    'url' => '/api/v1/files/{id}/rename',
                    'title' => 'Rename File',
                    'description' => 'Rename a file or folder.',
                    'params' => [],
                    'body' => [
                        ['name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'New name.'],
                    ],
                    'response' => '{
  "message": "File renamed",
  "file": File object
}',
                    'curl' => 'curl -s -X POST "https://your-domain.com/api/v1/files/01HXYZ.../rename" \\
  -H "Authorization: Bearer <your-token>" \\
  -H "Content-Type: application/json" \\
  -d \'{"name": "vacation-photo.jpg"}\'',
                ],
                [
                    'method' => 'POST',
                    'url' => '/api/v1/files/{id}/save',
                    'title' => 'Save File Content',
                    'description' => 'Overwrite the content of a text file. Only works on text or empty files.',
                    'params' => [],
                    'body' => [
                        ['name' => 'content', 'type' => 'string', 'required' => true, 'description' => 'New file content.'],
                    ],
                    'response' => '{
  "message": "File saved",
  "file": File object
}',
                    'curl' => 'curl -s -X POST "https://your-domain.com/api/v1/files/01HXYZ.../save" \\
  -H "Authorization: Bearer <your-token>" \\
  -H "Content-Type: application/json" \\
  -d \'{"content": "Hello world"}\'',
                ],
            ],
        ],
        [
            'title' => 'Search',
            'description' => 'Search files by name.',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'url' => '/api/v1/search',
                    'title' => 'Search Files',
                    'description' => 'Search files by name across your drive. Returns paginated results.',
                    'params' => [
                        ['name' => 'q', 'type' => 'string', 'required' => true, 'description' => 'Search query.'],
                        ['name' => 'per_page', 'type' => 'integer', 'required' => false, 'description' => 'Results per page (default: 50).'],
                    ],
                    'body' => null,
                    'response' => '{
  "files": [ File object, ... ],
  "links": { ... },
  "meta":  { ... }
}',
                    'curl' => 'curl -s "https://your-domain.com/api/v1/search?q=vacation" \\
  -H "Authorization: Bearer <your-token>"',
                ],
            ],
        ],
        [
            'title' => 'Favorites',
            'description' => 'Manage your favorite files.',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'url' => '/api/v1/favorites',
                    'title' => 'List Favorites',
                    'description' => 'List all your favorited files. Returns paginated results ordered by most recently favorited.',
                    'params' => [
                        ['name' => 'per_page', 'type' => 'integer', 'required' => false, 'description' => 'Results per page (default: 50).'],
                    ],
                    'body' => null,
                    'response' => '{
  "favorites": [
    { "id": "01HXYZ...", "favorited_at": "2024-01-01T00:00:00Z",
      "local_file": { "id": "01HXYZ...", "filename": "photo.jpg", "public_path": "", "is_dir": false } }
  ],
  "links": { ... },
  "meta": { ... }
}',
                    'curl' => 'curl -s "https://your-domain.com/api/v1/favorites" \\
  -H "Authorization: Bearer <your-token>"',
                ],
                [
                    'method' => 'POST',
                    'url' => '/api/v1/favorites',
                    'title' => 'Add Favorites',
                    'description' => 'Add one or more files to your favorites.',
                    'params' => [],
                    'body' => [
                        ['name' => 'local_file_ids[]', 'type' => 'string[]', 'required' => true, 'description' => 'Array of file ULIDs to favorite.'],
                    ],
                    'response' => '{
  "favorites": [...]
}',
                    'curl' => 'curl -s -X POST "https://your-domain.com/api/v1/favorites" \\
  -H "Authorization: Bearer <your-token>" \\
  -H "Content-Type: application/json" \\
  -d \'{"local_file_ids": ["01HXYZ...", "01HABC..."]}\'',
                ],
                [
                    'method' => 'DELETE',
                    'url' => '/api/v1/favorites/{id}',
                    'title' => 'Remove Favorite',
                    'description' => 'Remove a file from your favorites by favorite ID.',
                    'params' => [],
                    'body' => null,
                    'response' => '{
  "message": "Favorite removed"
}',
                    'curl' => 'curl -s -X DELETE "https://your-domain.com/api/v1/favorites/01HXYZ..." \\
  -H "Authorization: Bearer <your-token>"',
                ],
            ],
        ],
        [
            'title' => 'Shares',
            'description' => 'Create and manage public share links.',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'url' => '/api/v1/shares',
                    'title' => 'List Shares',
                    'description' => 'List all non-expired shares. Returns paginated results.',
                    'params' => [
                        ['name' => 'per_page', 'type' => 'integer', 'required' => false, 'description' => 'Results per page (default: 50).'],
                    ],
                    'body' => null,
                    'response' => '{
  "shares": [
    { "id": 1, "slug": "abc123", "enabled": true, "has_password": false, ... }
  ],
  "links": { ... },
  "meta": { ... }
}',
                    'curl' => 'curl -s "https://your-domain.com/api/v1/shares" \\
  -H "Authorization: Bearer <your-token>"',
                ],
                [
                    'method' => 'POST',
                    'url' => '/api/v1/shares',
                    'title' => 'Create Share',
                    'description' => 'Create a public share link for one or more files. A random slug is generated if not provided.',
                    'params' => [],
                    'body' => [
                        ['name' => 'fileList[]', 'type' => 'string[]', 'required' => true, 'description' => 'Array of file ULIDs to share.'],
                        ['name' => 'slug', 'type' => 'string', 'required' => false, 'description' => 'Custom slug (max 20 chars, no spaces/slashes). Random if omitted.'],
                        ['name' => 'password', 'type' => 'string', 'required' => false, 'description' => 'Password to protect the share (min 6 chars).'],
                        ['name' => 'expiry', 'type' => 'integer', 'required' => false, 'description' => 'Expiry in days from now.'],
                    ],
                    'response' => '{
  "share": { "id": 1, "slug": "abc123", "enabled": true, ... },
  "url": "https://your-domain.com/shared/abc123"
}',
                    'curl' => 'curl -s -X POST "https://your-domain.com/api/v1/shares" \\
  -H "Authorization: Bearer <your-token>" \\
  -H "Content-Type: application/json" \\
  -d \'{"fileList": ["01HXYZ..."], "slug": "my-share", "password": "secret123", "expiry": 7}\'',
                ],
                [
                    'method' => 'DELETE',
                    'url' => '/api/v1/shares/{id}',
                    'title' => 'Delete Share',
                    'description' => 'Delete a share link by ID.',
                    'params' => [],
                    'body' => null,
                    'response' => '{
  "message": "Share deleted"
}',
                    'curl' => 'curl -s -X DELETE "https://your-domain.com/api/v1/shares/1" \\
  -H "Authorization: Bearer <your-token>"',
                ],
                [
                    'method' => 'POST',
                    'url' => '/api/v1/shares/{id}/toggle',
                    'title' => 'Toggle Share',
                    'description' => 'Enable or disable a share link without deleting it.',
                    'params' => [],
                    'body' => null,
                    'response' => '{
  "share": { "id": 1, "enabled": false, ... },
  "message": "Share paused"
}',
                    'curl' => 'curl -s -X POST "https://your-domain.com/api/v1/shares/1/toggle" \\
  -H "Authorization: Bearer <your-token>"',
                ],
            ],
        ],
        [
            'title' => 'Objects',
            'description' => 'Shared response shapes referenced throughout this documentation. Endpoints below return these objects rather than repeating the full shape each time.',
            'endpoints' => [
                [
                    'title' => 'File object',
                    'description' => 'Returned by every file/folder endpoint, standalone as "file" or inside a "files" array. "size" is the raw byte count (integer); format it client-side. "date" is the last-modified time as a Unix timestamp (seconds). Folders have "is_dir": true and "size": 0.',
                    'response' => '{
  "id": "01HXYZ...",
  "filename": "photo.jpg",
  "is_dir": false,
  "public_path": "Documents",
  "size": 2516582,
  "created_at": "2024-01-15T10:30:00.000000Z",
  "updated_at": "2024-01-15T10:30:00.000000Z",
  "date": 1693500000
}',
                ],
                [
                    'title' => 'Pagination',
                    'description' => 'List endpoints (files, search, favorites, shares) return standard Laravel pagination alongside the data array. "links" holds page URLs (null when absent), "meta" holds page counters. Request a specific page with ?page=N and set page size with ?per_page=N.',
                    'response' => '{
  "links": {
    "first": "https://your-domain.com/api/v1/files?page=1",
    "last":  "https://your-domain.com/api/v1/files?page=3",
    "prev":  null,
    "next":  "https://your-domain.com/api/v1/files?page=2"
  },
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 50,
    "total": 120
  }
}',
                ],
            ],
        ],
    ],
];
