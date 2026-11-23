<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Fields\FieldTypes;
use App\Libraries\EntryCsv;
use App\Libraries\EntryListQuery;
use App\Libraries\ModelListPage;

/**
 * The list of the entries of one model, drawn on the server. A request from htmx for `#entries` gets only the list, so a
 * sort, a search, a filter or a page swaps the list and leaves the rest of the page where it is.
 */
class Model extends AdminController
{
    public function index($id)
    {
        $model = $this->openModel($id);
        if (! is_array($model)) {
            return $model;
        }

        $data = (new ModelListPage(FieldTypes::core()))->viewData($model, $this->request->getGet(), $this->perPage());

        // A module may change the address of the New button, as the paging system does for its page models.
        $data = $this->hooks->filter(hook('Backend.controller:model:index:data'), $data);

        // A swap of the list gets the list alone. The browser's own restore of a page after Back needs the whole page.
        if (self::wantsTheList($this->request)) {
            return view('admin/partials/entry_results', $data);
        }

        $this->data = array_merge($this->data, $data, [
            'title'  => $model['name'],
            'crumbs' => [[lang('Admin.entries'), base_url('admin/entries')], [$model['name'], null]],
        ]);

        return view('admin/model', $this->data);
    }

    /**
     * The entries of the list as a CSV file, under the same search, sort and filters as the page.
     */
    public function export($id)
    {
        $model = $this->openModel($id);
        if (! is_array($model)) {
            return $model;
        }

        $types  = FieldTypes::core();
        $page   = new ModelListPage($types);
        $query  = new EntryListQuery(service('entryStore'), $types);
        $fields = $page->definition((string) $model['fields']);

        $request = $query->parse($this->request->getGet(), $fields, 100);
        $request['per'] = 100;

        $header = [lang('Entries.entryNumber')];
        foreach ($fields as $field) {
            $header[] = (string) ($field['label'] ?? $field['id']);
        }
        $header[] = lang('Entries.edited');
        $header[] = lang('Entries.editedBy');

        // The rows come a page at a time, and the file stops at a fixed number so one request cannot read a whole database.
        $rows = [];
        for ($request['page'] = 1; $request['page'] <= 1000; $request['page']++) {
            $result = $query->run((int) $id, $request);
            if ($result['error'] !== '') {
                return redirect()->to(base_url('admin/model/' . (int) $id))->with('error', $result['error']);
            }
            foreach ($result['rows'] as $row) {
                $line = [(string) $row['id']];
                foreach ($fields as $field) {
                    $line[] = EntryCsv::value($row['fields'][$field['id']] ?? null, $field);
                }
                $line[] = (string) $row['date_modified'];
                $line[] = EntryCsv::safe((string) ($row['edited_by'] ?? ''));
                $rows[] = $line;
            }
            if (count($result['rows']) < $request['per']) {
                break;
            }
        }

        $name = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $model['name']) ?: 'entries';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '-' . date('Y-m-d') . '.csv"')
            ->setBody(EntryCsv::write($header, $rows));
    }

    /**
     * The model as a row, or the redirect to send the person to when there is none or they may not open it.
     *
     * @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse
     */
    private function openModel(mixed $id)
    {
        $model = is_numeric($id) ? service('modelStore')->findArray((int) $id) : false;
        if (! $model) {
            return redirect()->to(base_url('admin/entries'))->with('error', lang('Entries.noModel'));
        }
        if (! can('entries.read', (int) $id)) {
            return redirect()->to(base_url('admin/dashboard'))->with('error', lang('Auth.notEnoughPrivilege'));
        }

        return $model;
    }

    private function perPage(): int
    {
        return (int) (service('settings')->get('App.datatableEntriesPerPage', 'user:' . user_id()) ?: 25);
    }

    /**
     * Whether the request asks for the list alone: htmx sent it for `#entries` and is not restoring a page of the history.
     */
    public static function wantsTheList($request, string $target = 'entries'): bool
    {
        return $request->hasHeader('HX-Request')
            && ! $request->hasHeader('HX-History-Restore-Request')
            && $request->getHeaderLine('HX-Target') === $target;
    }
}
