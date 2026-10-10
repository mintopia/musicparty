<?php

namespace App\Http\Controllers\Party;

use App\Domain\Party\Models\Party;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Stats\Actions\BuildPartyPlaylistCsv;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlaylistCsvController extends Controller
{
    public function __invoke(Party $party, BuildPartyPlaylistCsv $buildCsv): StreamedResponse
    {
        $this->authorize('exportPlaylist', $party);

        try {
            $rows = $buildCsv($party);
        } catch (RequestRefusedException $exception) {
            abort($exception->getCode(), $exception->getMessage());
        }

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }

            fclose($out);
        }, strtolower($party->code).'-playlist.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
