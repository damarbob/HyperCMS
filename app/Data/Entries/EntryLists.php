<?php

namespace App\Data\Entries;

use LogicException;

/**
 * Lists of entries for the admin tables and the token API: the fixed-column path in SQL over
 * hyper_entries, and the saved lists for search, find filters and sorts on a model field.
 *
 * This is a skeleton. Phase A6 fills it in and owns this file.
 */
class EntryLists
{
    /**
     * Answer one DataTables request for entries across all models.
     *
     * @param array $post The POST values DataTables sent (id, ids, model_id, draw, start, length, search, order, columns, trash).
     * @param array $hiddenModelIds Models whose entries the caller may not read.
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     */
    public function dataTable(array $post, array $hiddenModelIds = []): array
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Answer one DataTables request for the entries of a single model.
     *
     * @param array $params The values to read (id, draw, start, length, search, order, columns, find, trash).
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     * @throws \InvalidArgumentException When the model id is missing or names no model.
     */
    public function modelDataTable(array $params): array
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Answer one DataTables request for the saved versions of one entry.
     *
     * @param array $post The POST values DataTables sent (id, draw, start, length, search, order, columns, find).
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     * @throws \App\Data\LookupException When the entry id is missing or names no entry, or the entry names no model.
     */
    public function versionsDataTable(array $post): array
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Read one page of entries for the token API.
     *
     * @param array $filters The optional `model_id`, `search` and `hidden_model_ids`.
     * @param string $sortColumn A column from the caller's own fixed list, never text from a request.
     * @param string $direction ASC or DESC.
     * @param int $limit Entries per page.
     * @param int $page The page number, starting at 1.
     * @return array The rows under `data` and the count of all matching entries under `total`.
     */
    public function page(array $filters, string $sortColumn, string $direction, int $limit, int $page): array
    {
        throw $this->notPorted(__FUNCTION__);
    }

    private function notPorted(string $method): LogicException
    {
        return new LogicException(static::class . '::' . $method . '() is not ported to StarDust 0.3 yet.');
    }
}
