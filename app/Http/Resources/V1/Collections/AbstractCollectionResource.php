<?php
namespace App\Http\Resources\V1\Collections;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @mixin LengthAwarePaginator
 */
abstract class AbstractCollectionResource extends ResourceCollection
{
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'pagination' => (object)[
                'total' => $default['meta']['total'],
                'count' => $this->count(),
                'perPage' => $default['meta']['per_page'],
                'currentPage' => $default['meta']['current_page'],
                'totalPages' => $default['meta']['last_page'],
            ],
            'links' => (object)[
                'self' => $request->getUri(),
                'first' => $default['links']['first'],
                'last' => $default['links']['last'],
                'previous' => $default['links']['prev'],
                'next' => $default['links']['next'],
            ],
        ];
    }
}
