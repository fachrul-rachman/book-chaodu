<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveClient
{
    public function __construct(
        private readonly GoogleServiceAccountAccessTokenService $accessTokenService,
    ) {}

    /**
     * @return array{id:string,url:string}
     */
    public function ensureFolder(string $bookingNumber): array
    {
        $rootFolderId = (string) config('phase7.google.root_folder_id');

        if ($rootFolderId === '') {
            throw new RuntimeException('Folder utama Google Drive belum diisi.');
        }

        $existing = $this->findFolder($bookingNumber, $rootFolderId);

        if ($existing !== null) {
            return $existing;
        }

        $response = $this->request()
            ->post($this->baseUrl().'/drive/v3/files?fields=id,webViewLink', [
                'name' => $bookingNumber,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$rootFolderId],
            ])
            ->throw();

        $folderId = (string) $response->json('id');

        if ($folderId === '') {
            throw new RuntimeException('Folder Google Drive tidak berhasil dibuat.');
        }

        if ((bool) config('phase7.google.share_anyone_with_link')) {
            $this->request()
                ->post($this->baseUrl().'/drive/v3/files/'.$folderId.'/permissions', [
                    'role' => 'reader',
                    'type' => 'anyone',
                ])
                ->throw();
        }

        return [
            'id' => $folderId,
            'url' => (string) $response->json('webViewLink', 'https://drive.google.com/drive/folders/'.$folderId),
        ];
    }

    /**
     * @return array{id:string,url:string}|null
     */
    private function findFolder(string $bookingNumber, string $rootFolderId): ?array
    {
        $query = sprintf(
            "name = '%s' and mimeType = 'application/vnd.google-apps.folder' and '%s' in parents and trashed = false",
            str_replace("'", "\\'", $bookingNumber),
            str_replace("'", "\\'", $rootFolderId),
        );

        $response = $this->request()
            ->get($this->baseUrl().'/drive/v3/files', [
                'q' => $query,
                'pageSize' => 1,
                'fields' => 'files(id, webViewLink)',
            ])
            ->throw();

        $file = $response->json('files.0');

        if (! is_array($file)) {
            return null;
        }

        $folderId = (string) ($file['id'] ?? '');

        if ($folderId === '') {
            return null;
        }

        return [
            'id' => $folderId,
            'url' => (string) ($file['webViewLink'] ?? 'https://drive.google.com/drive/folders/'.$folderId),
        ];
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->accessTokenService->getAccessToken())
            ->acceptJson()
            ->timeout((int) config('phase7.google.timeout_seconds'));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('phase7.google.base_url'), '/');
    }
}
