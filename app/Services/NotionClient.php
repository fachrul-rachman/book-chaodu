<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NotionClient
{
    /**
     * @param  array<int, string>  $detailLines
     * @return array{id:string,url:string}
     */
    public function ensureBookingPage(string $bookingNumber, array $detailLines, ?string $existingId = null): array
    {
        if ($existingId) {
            $response = $this->request()
                ->get($this->baseUrl().'/pages/'.$existingId)
                ->throw();

            return [
                'id' => $existingId,
                'url' => (string) $response->json('url'),
            ];
        }

        $parentId = (string) config('phase7.notion.parent_id');
        $parentType = (string) config('phase7.notion.parent_type');

        if ($parentId === '') {
            throw new RuntimeException('Parent Notion belum diisi.');
        }

        if ($parentType !== 'page_id') {
            throw new RuntimeException('Parent Notion saat ini hanya mendukung page biasa.');
        }

        $response = $this->request()
            ->post($this->baseUrl().'/pages', [
                'parent' => [
                    $parentType => $parentId,
                ],
                'properties' => [
                    'title' => [
                        'title' => [[
                            'type' => 'text',
                            'text' => [
                                'content' => $bookingNumber,
                            ],
                        ]],
                    ],
                ],
                'children' => array_map(
                    fn (string $line): array => [
                        'object' => 'block',
                        'type' => 'paragraph',
                        'paragraph' => [
                            'rich_text' => [[
                                'type' => 'text',
                                'text' => [
                                    'content' => $line,
                                ],
                            ]],
                        ],
                    ],
                    $detailLines,
                ),
            ])
            ->throw();

        $pageId = (string) $response->json('id');

        if ($pageId === '') {
            throw new RuntimeException('Halaman Notion tidak berhasil dibuat.');
        }

        return [
            'id' => $pageId,
            'url' => (string) $response->json('url'),
        ];
    }

    private function request(): PendingRequest
    {
        $token = (string) config('phase7.notion.api_token');

        if ($token === '') {
            throw new RuntimeException('Token Notion belum diisi.');
        }

        return Http::withToken($token)
            ->acceptJson()
            ->withHeaders([
                'Notion-Version' => (string) config('phase7.notion.version'),
            ])
            ->timeout((int) config('phase7.notion.timeout_seconds'));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('phase7.notion.base_url'), '/');
    }
}
