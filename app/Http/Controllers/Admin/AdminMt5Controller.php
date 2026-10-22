<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mt5Account;
use App\Models\Mt5Request;
use App\Services\Mt5BrokerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminMt5Controller extends Controller
{
    public function index(Request $request)
    {
        $requests = Mt5Request::orderBy('created_at', 'desc')->get();
        $accounts = Mt5Account::orderBy('created_at', 'desc')->get()->keyBy('user_uid');

        $usersMap = DB::connection('mongodb')
            ->table('users')
            ->get()
            ->keyBy(function ($u) {
                return (string) ($u['_id'] ?? $u['uid'] ?? '');
            });

        $requestsData = $requests->map(function ($req) use ($usersMap, $accounts) {
            $user = $usersMap->get($req->user_uid);
            $account = $accounts->get($req->user_uid);

            return [
                'id' => (string) $req->_id,
                'request_uid' => $req->request_uid,
                'user_uid' => $req->user_uid,
                'user_name' => $user['name'] ?? 'Unknown User',
                'user_email' => $user['email'] ?? 'N/A',
                'plan_name' => $req->plan_name,
                'minimum_balance' => $req->minimum_balance,
                'status' => $req->status,
                'requested_at' => $req->requested_at ? $req->requested_at->toDateTimeString() : null,
                'account' => $account ? $account->toArray() : null,
            ];
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => $requestsData,
            ]);
        }

        return view('admin.mt5-requests.index', [
            'requests' => $requestsData,
        ]);
    }

    public function assignCredentials(Request $request, string $uid, Mt5BrokerService $service)
    {
        $login = (string) $request->input('login', '');
        $password = (string) $request->input('password', '');
        $server = (string) $request->input('server', 'Kryvex-Live-01');
        $investorPassword = (string) $request->input('investor_password', '');

        if (empty($login) || empty($password)) {
            return response()->json([
                'success' => false,
                'message' => 'MT5 Login ID and Password are required.',
            ], 422);
        }

        try {
            $adminUser = session('admin_user') ?? ['uid' => 'admin'];
            $adminUid = is_array($adminUser) ? ($adminUser['uid'] ?? 'admin') : 'admin';

            $result = $service->assignCredentials(
                userUid: $uid,
                login: $login,
                password: $password,
                server: $server,
                investorPassword: $investorPassword,
                adminUid: $adminUid
            );

            if (!$request->wantsJson() && !$request->ajax()) {
                return redirect()->back()->with('success', 'MT5 credentials assigned successfully and email dispatched to user!');
            }

            return response()->json([
                'success' => true,
                'message' => 'MT5 credentials assigned successfully and email dispatched to user!',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            if (!$request->wantsJson() && !$request->ajax()) {
                return redirect()->back()->with('error', $e->getMessage());
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
