<?php

namespace App\Services;

use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CompactTable
{
    /** Column names are supplied by the server, never trusted from the browser. */
    public static function query($query, Request $request, array $columns, array $searchable)
    {
        $request->merge(['length' => max(1, min(100, (int) $request->input('length', 10))), 'start' => max(0, (int) $request->input('start', 0)), 'draw' => max(0, (int) $request->input('draw', 0))]);

        return DataTables::eloquent($query)
            ->filter(function ($query) use ($request, $searchable) {
                $search = mb_substr((string) $request->input('search.value', ''), 0, 200);
                if ($search !== '') {
                    $query->where(function ($nested) use ($search, $searchable) {
                        foreach ($searchable as $column) {
                            $nested->orWhere($column, 'like', '%'.$search.'%');
                        }
                    });
                }
            })
            ->order(function ($query) use ($request, $columns) {
                $column = $columns[(int) $request->input('order.0.column', 0)] ?? null;
                $query->reorder()->orderBy($column ?: $columns[0], $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc')->orderBy($query->getModel()->qualifyColumn('id'));
            });
    }
}
