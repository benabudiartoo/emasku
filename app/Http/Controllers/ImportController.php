<?php

namespace App\Http\Controllers;

use App\Services\Import\ExcelItemImporter;
use App\Services\Import\ImportException;
use App\Services\Import\ImportPreview;
use App\Services\Import\ImportTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Importing jewelry from an Excel workbook: upload, preview, save. Nothing is created until the owner saves, and the
 * save step uses the rows read on the server (see ImportPreview), never data sent back by the browser.
 */
class ImportController extends Controller
{
    private const MAX_KB = 2048;

    public function __construct(private ImportPreview $preview) {}

    public function create(): Response
    {
        return Inertia::render('Koleksi/Impor', ['max_mb' => self::MAX_KB / 1024, 'max_rows' => ExcelItemImporter::MAX_ROWS]);
    }

    /** The example workbook: the format the importer reads, with three made-up rows and a Petunjuk sheet. */
    public function template(ImportTemplate $template): HttpResponse
    {
        return response($template->render(), 200, [
            'Content-Type' => ImportTemplate::MIME,
            'Content-Disposition' => 'attachment; filename="'.ImportTemplate::FILENAME.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request, ExcelItemImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:'.self::MAX_KB],
        ], [
            'file.required' => 'Pilih berkas Excel (.xlsx) lebih dulu.',
            'file.file' => 'Berkas tidak valid.',
            'file.mimes' => 'Hanya berkas Excel (.xlsx) yang bisa diimpor.',
            'file.max' => 'Berkas lebih besar dari 2 MB.',
        ]);

        $file = $request->file('file');
        // An .xlsx is a zip archive: check the signature as well as the name.
        if (file_get_contents($file->getRealPath(), false, null, 0, 2) !== 'PK') {
            return back()->withErrors(['file' => 'Hanya berkas Excel (.xlsx) yang bisa diimpor.']);
        }

        try {
            $parsed = $importer->read($file->getRealPath(), $request->user()->id);
        } catch (ImportException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $token = $this->preview->put($request->user()->id, $parsed, $file->getClientOriginalName());

        return redirect("/koleksi/impor/{$token}");
    }

    public function show(Request $request, string $token): Response
    {
        $data = $this->preview->get($request->user()->id, $token);
        abort_if($data === null, 404);

        $sheet = $request->query('sheet');
        $sheet = is_string($sheet) && isset($data['sheets'][$sheet]) ? $sheet : $data['default'];

        $sheets = [];
        foreach ($data['sheets'] as $name => $rows) {
            $sheets[] = ['name' => $name, 'count' => count($rows)];
        }

        $rows = $data['sheets'][$sheet];

        return Inertia::render('Koleksi/ImporPratinjau', [
            'token' => $token,
            'filename' => $data['filename'],
            'sheets' => $sheets,
            'sheet' => $sheet,
            'rows' => $rows,
            'counts' => array_count_values(array_column($rows, 'status')),
        ]);
    }

    public function save(Request $request, string $token): RedirectResponse
    {
        $data = $this->preview->get($request->user()->id, $token);
        abort_if($data === null, 404);

        $input = $request->validate([
            'sheet' => ['required', 'string', 'max:255'],
            'rows' => ['nullable', 'array', 'max:'.ExcelItemImporter::MAX_ROWS],
            'rows.*' => ['integer', 'min:1'],
        ]);

        if (! isset($data['sheets'][$input['sheet']])) {
            abort(404);
        }
        if (empty($input['rows'])) {
            return back()->with('toast', ['type' => 'error', 'message' => 'Centang minimal satu baris untuk diimpor']);
        }

        $result = $this->preview->save($request->user(), $token, $input['sheet'], $input['rows']);

        $message = $result['created'].' barang diimpor';
        if ($result['skipped'] > 0) {
            $message .= ", {$result['skipped']} dilewati karena sudah ada";
        }

        return redirect('/koleksi')->with('toast', ['type' => 'success', 'message' => $message]);
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        $this->preview->forget($request->user()->id, $token);

        return redirect('/koleksi/impor')->with('toast', ['type' => 'info', 'message' => 'Impor dibatalkan']);
    }
}
