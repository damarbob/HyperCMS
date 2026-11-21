<?php

namespace App\Data;

use App\Data\Entries\EntryLists;
use App\Data\Entries\EntryReader;
use App\Data\Entries\EntryRows;
use App\Data\Entries\EntryWriter;

/**
 * The one place that reads and writes entries.
 *
 * Controllers call this class and never touch StarDust themselves, so the engine behind it can
 * change without editing every controller. The work is split by lane: EntryWriter writes,
 * EntryReader answers single and module reads, EntryLists answers lists and saved lists, and
 * EntryRows builds the row every read returns.
 */
class EntryStore
{
    public function __construct(
        private ?EntryWriter $writer = null,
        private ?EntryReader $reader = null,
        private ?EntryLists $lists = null,
    ) {
        $this->writer ??= new EntryWriter();
        $this->reader ??= new EntryReader();
        $this->lists ??= new EntryLists();
    }

    /**
     * Read one live entry as a row with StarDust's own column names, or false when there is none.
     */
    public function findArray(int $id): array|false
    {
        return $this->reader->findArray($id);
    }

    /**
     * Create an entry and return its id.
     */
    public function create(array $data, int $userId): int
    {
        return $this->writer->create($data, $userId);
    }

    /**
     * Save a new version of an entry.
     */
    public function update(int $id, array $data, int $userId): void
    {
        $this->writer->update($id, $data, $userId);
    }

    /**
     * Move entries to the trash.
     */
    public function trash(array $ids, int $userId): void
    {
        $this->writer->trash($ids, $userId);
    }

    /**
     * Read the trashed entries among the given ids, or false when there are none.
     */
    public function trashed(array $ids): array|false
    {
        return $this->reader->trashed($ids);
    }

    /**
     * Count the entries in the trash.
     */
    public function countTrashed(): int|string
    {
        return $this->reader->countTrashed();
    }

    /**
     * Delete the trashed entries for good.
     */
    public function emptyTrash(): void
    {
        $this->writer->emptyTrash();
    }

    /**
     * Bring entries back from the trash.
     */
    public function restore(array $ids): void
    {
        $this->writer->restore($ids);
    }

    /**
     * Read every live entry as rows with StarDust's own column names.
     */
    public function allArray(): array
    {
        return $this->reader->allArray();
    }

    /**
     * Read the live entries of the given models, with the legacy column names (model_name, fields, date_modified).
     */
    public function ofModels(array $modelIds): array
    {
        return $this->reader->ofModels($modelIds);
    }

    /**
     * Read the live entries of the model with this name, with the legacy column names.
     */
    public function named(string $modelName): array
    {
        return $this->reader->named($modelName);
    }

    /**
     * Read live entries newest first, with the legacy column names.
     *
     * @param array $filters Optional keys: `id`, `ids` (a list), `model_id`, and `hidden_model_ids` (a list of models to leave out). A filter that is empty is skipped.
     */
    public function recent(array $filters = []): array
    {
        return $this->reader->recent($filters);
    }

    /**
     * Read the newest live entry of a model that has no field values, or null when there is none.
     *
     * A blank entry is one whose fields are null, an empty string, or an empty JSON array or object.
     */
    public function blankFor(int|string $modelId): ?array
    {
        return $this->reader->blankFor($modelId);
    }

    /**
     * Compile the SQL that reads every live entry as a flat table, without running it.
     *
     * The syntax processor wraps this SQL as a subquery so a form field can list entries.
     */
    public function compiledSelect(): string
    {
        return $this->reader->compiledSelect();
    }

    /**
     * Every live entry without its fields, as `id`, `model_id`, `model_name`, `sd_entry_id` and `payload_bytes`.
     *
     * @return list<array<string, mixed>>
     */
    public function flatRows(): array
    {
        return $this->reader->flatRows();
    }

    /**
     * Read the fields of rows from `flatRows()` a chunk at a time, yielding each chunk's fields by entry id.
     *
     * @param list<array<string, mixed>> $flatRows
     *
     * @return \Generator<int, array<int, array>>
     */
    public function fieldsOf(array $flatRows): \Generator
    {
        return $this->reader->fieldsOf($flatRows);
    }

    /**
     * Answer one DataTables request for entries across all models.
     *
     * @param array $post The POST values DataTables sent (id, ids, model_id, draw, start, length, search, order, columns, trash).
     * @param array $hiddenModelIds Models whose entries the caller may not read. They are left out of the rows and the counts.
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     */
    public function dataTable(array $post, array $hiddenModelIds = []): array
    {
        return $this->lists->dataTable($post, $hiddenModelIds);
    }

    /**
     * Read one live entry with the legacy column names, or null when there is none.
     *
     * This is the row the admin pages use. `findRow()` is the token API's version of it.
     */
    public function findAliased(int|string $id): ?array
    {
        return $this->reader->findAliased($id);
    }

    /**
     * Read the saved versions of one entry as rows.
     */
    public function versionsOf(int|string $entryId): array
    {
        return $this->reader->versionsOf($entryId);
    }

    /**
     * Delete every saved version of an entry except the newest, for good.
     *
     * @return int|null The number of versions deleted, or null when the entry has no versions.
     */
    public function clearHistory(int|string $entryId): ?int
    {
        return $this->writer->clearHistory($entryId);
    }

    /**
     * Count the live entries of one model.
     */
    public function countForModel(int|string $modelId): int
    {
        return $this->reader->countForModel($modelId);
    }

    /**
     * Read one page of entries for the token API.
     *
     * @param array $filters The optional `model_id`, `search` text that matches the model name or the creator's username,
     *                       and `hidden_model_ids`, a list of models whose entries are left out.
     * @param string $sortColumn A column from the caller's own fixed list, never text from a request.
     * @param string $direction ASC or DESC.
     * @param int $limit Entries per page.
     * @param int $page The page number, starting at 1.
     * @return array The rows under `data` and the count of all matching entries under `total`.
     */
    public function page(array $filters, string $sortColumn, string $direction, int $limit, int $page): array
    {
        return $this->lists->page($filters, $sortColumn, $direction, $limit, $page);
    }

    /**
     * The fields of a model that lists can sort and filter on right now, with the StarDust type of each.
     *
     * @return array<string, string> By field id.
     */
    public function indexedFields(int $modelId): array
    {
        return $this->lists->indexedFields($modelId);
    }

    /**
     * Read one page of a model's entries through its indexed fields, for the token API.
     *
     * @param int $countCap The most matches to count for the total.
     *
     * @return array{data: list<array>, total: int|null, more: bool} `total` is null when the count stopped at the cap.
     */
    public function fieldPage(int $modelId, array $hiddenModelIds, ?\StarDust\Read\SortSpec $sort, ?\StarDust\Filter\Ast\FilterNode $filter, int $limit, int $page, int $countCap): array
    {
        return $this->lists->fieldPage($modelId, $hiddenModelIds, $sort, $filter, $limit, $page, $countCap);
    }

    /**
     * Read one entry by its id, or null when there is none.
     */
    public function findRow($id): ?array
    {
        return $this->reader->findRow($id);
    }

    /**
     * Read the id of the model an entry belongs to, or null when the entry has no saved version.
     */
    public function modelIdOf(int|string $entryId): ?int
    {
        return $this->reader->modelIdOf($entryId);
    }

    /**
     * Answer one DataTables request for the entries of a single model.
     *
     * The caller may be a controller or another module that passes the values directly.
     *
     * @param array $params The values to read (id, draw, start, length, search, order, columns, find, trash).
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     * @throws \InvalidArgumentException When the model id is missing or names no model.
     */
    public function modelDataTable(array $params): array
    {
        return $this->lists->modelDataTable($params);
    }

    /**
     * Answer one DataTables request for the saved versions of one entry.
     *
     * @param array $post The POST values DataTables sent (id, draw, start, length, search, order, columns, find).
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     * @throws LookupException When the entry id is missing or names no entry, or the entry names no model.
     */
    public function versionsDataTable(array $post): array
    {
        return $this->lists->versionsDataTable($post);
    }
}
