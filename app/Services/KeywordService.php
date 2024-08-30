<?php

namespace App\Services;

use App\Models\Keyword;
use Illuminate\Support\Collection;


class KeywordService
{
    public function __construct(
        private readonly ProtocolService $protocolService
    )
    {
    }

    public function create(array $data): Collection
    {
        $currentTime = now();
        $newKeywords = [];

        foreach ($data['keywords'] as $keyword) {
            $newKeywords[] = [
                'title' => str_replace("\n", "", $keyword['title']),
                'phrase' => str_replace("\n", "", $keyword['phrase']),
                'user_id' => auth()->id(),
                'created_at' => $currentTime,
                'updated_at' => $currentTime,
            ];
        }

        Keyword::insert($newKeywords);

        $this->updateUserProtocols();

        return Keyword::where('created_at', $currentTime)->get();
    }

    /**
     * @param Keyword|null $updatedKeyword
     * @param Keyword|null $deletedKeyword
     * @return void
     */
    public function updateUserProtocols(?Keyword $updatedKeyword = null, ?Keyword $deletedKeyword = null): void
    {
        $chunkSize = 100;

        auth()->user()->protocols()
            ->chunkById($chunkSize, function ($protocols) use ($updatedKeyword, $deletedKeyword) {
                foreach ($protocols as $protocol) {
                    $currentFinalTranscript = $protocol->final_transcript;

                    if ($deletedKeyword) {
                        $currentFinalTranscript = array_values(array_filter($currentFinalTranscript, function ($item) use ($deletedKeyword) {
                            return $item['key'] !== $deletedKeyword->title || !empty($item['value']);
                        }));
                    } else {
                        $transcript = is_null($protocol->transcript) ? null : $protocol->transcript['text'];
                        $currentFinalTranscript = $this->protocolService->getFinalTranscript(
                            $transcript,
                            auth()->id(),
                            $currentFinalTranscript,
                            $updatedKeyword
                        );
                    }

                    $protocol->update(['final_transcript' => $currentFinalTranscript]);
                }
            });
    }
}
