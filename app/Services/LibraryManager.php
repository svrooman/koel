<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use Illuminate\Support\Facades\DB;

class LibraryManager
{
    /**
     * Delete albums and artists that have no songs.
     *
     * @return array<mixed>
     */
    public function prune(bool $dryRun = false): array
    {
        return DB::transaction(static function () use ($dryRun): array {
            // Query to identify duplicate songs based on path and name
            $duplicateSongsQuery = Song::query()
                ->select('id')
                ->whereIn('id', function ($query) {
                    $query->selectRaw('MIN(id)')
                        ->from('songs')
                        ->groupBy('path', 'title')
                        ->havingRaw('COUNT(*) > 1');
                });

            if (!$dryRun) {
                Song::query()->whereIn('id', $duplicateSongsQuery)->delete();
            }

            // Query to delete orphaned albums
            $albumQuery = Album::query()
                ->leftJoin('songs', 'songs.album_id', '=', 'albums.id')
                ->whereNull('songs.album_id')
                ->whereNotIn('albums.id', [Album::UNKNOWN_ID]);

            // Query to delete orphaned artists
            $artistQuery = Artist::query()
                ->leftJoin('songs', 'songs.artist_id', '=', 'artists.id')
                ->leftJoin('albums', 'albums.artist_id', '=', 'artists.id')
                ->whereNull('songs.artist_id')
                ->whereNull('albums.artist_id')
                ->whereNotIn('artists.id', [Artist::UNKNOWN_ID, Artist::VARIOUS_ID]);

            // Collect results before deleting (if not in dry run mode)
            $results = [
                'albums' => $albumQuery->get('albums.*'),
                'artists' => $artistQuery->get('artists.*'),
                'duplicate_songs' => Song::query()->whereIn('id', $duplicateSongsQuery)->get(),
            ];

            if (!$dryRun) {
                $albumQuery->delete();
                $artistQuery->delete();
            }

            return $results;
        });
    }
}
