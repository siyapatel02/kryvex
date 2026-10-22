<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $search = strtolower(trim((string) $request->query('search')));
        $module = $request->query('module', 'all');
        $action = $request->query('action', 'all');

        $logs = AdminPaginator::paginate($this->logsQuery($search, $module, $action), $request, 10);

        return view('admin.audit.index', compact(
            'logs',
            'search',
            'module',
            'action'
        ));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $search = strtolower(trim((string) $request->query('search')));
        $module = $request->query('module', 'all');
        $action = $request->query('action', 'all');

        $logs = $this->logsQuery($search, $module, $action);

        $filename = 'audit_logs_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Admin Email',
                'Module',
                'Action',
                'Message',
                'IP Address',
                'User Agent',
                'Meta',
                'Date',
            ]);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->admin_email ?? '-',
                    $log->module ?? '-',
                    $log->action ?? '-',
                    $log->message ?? '-',
                    $log->ip_address ?? '-',
                    $log->user_agent ?? '-',
                    json_encode($log->meta ?? []),
                    isset($log->created_at)
                        ? \Carbon\Carbon::parse($log->created_at)->format('d M Y, h:i A')
                        : '-',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function logsQuery(string $search, string $module, string $action)
    {
        return DB::connection('mongodb')
            ->table('audit_logs')
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get()
            ->filter(function ($log) use ($search, $module, $action) {
                if ($module !== 'all' && ($log->module ?? '') !== $module) {
                    return false;
                }

                if ($action !== 'all' && ($log->action ?? '') !== $action) {
                    return false;
                }

                if ($search !== '') {
                    $text = strtolower(implode(' ', [
                        $log->admin_email ?? '',
                        $log->action ?? '',
                        $log->module ?? '',
                        $log->message ?? '',
                        $log->ip_address ?? '',
                        json_encode($log->meta ?? []),
                    ]));

                    if (!str_contains($text, $search)) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }
}