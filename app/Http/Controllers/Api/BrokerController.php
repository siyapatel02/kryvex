<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Mt5BrokerService;
use Illuminate\Http\Request;

class BrokerController extends Controller
{
    public function status(Request $request, Mt5BrokerService $service)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        try {
            $data = $service->getUserBrokerStatus((string) $user['uid']);
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function select(Request $request, Mt5BrokerService $service)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $brokerType = (string) $request->input('broker_type', 'kryvex');
        $planKey = $request->input('plan_key');

        try {
            $data = $service->selectBroker((string) $user['uid'], $brokerType, $planKey ? (string) $planKey : null);
            
            // Auto-create/sync wallet and trading account for selected broker
            $wallet = app(\App\Services\DemoWalletService::class)->getOrCreateWallet((string) $user['uid'], null, $brokerType);
            $data['wallet'] = $wallet;

            return response()->json([
                'success' => true,
                'message' => 'Trading broker updated successfully.',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function plans(Mt5BrokerService $service)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $service->getPlans(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function requestPlan(Request $request, Mt5BrokerService $service)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $planKey = (string) $request->input('plan_key', '');
        if (empty($planKey)) {
            return response()->json(['success' => false, 'message' => 'Please select a valid MT5 plan.'], 422);
        }

        try {
            $data = $service->requestPlan((string) $user['uid'], $planKey);
            return response()->json([
                'success' => true,
                'message' => 'MetaTrader 5 account requested successfully.',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function saveCredentials(Request $request, Mt5BrokerService $service)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $login = (string) $request->input('login', '');
        $password = (string) $request->input('password', '');
        $server = (string) $request->input('server', 'Kryvex-Live-01');

        if (empty($login) || empty($password)) {
            return response()->json(['success' => false, 'message' => 'MT5 Login ID and Password are required.'], 422);
        }

        try {
            $data = $service->saveUserCredentials((string) $user['uid'], $login, $password, $server);

            if (!empty($data['metaapi_warning'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'MT5 Broker connection failed: ' . $data['metaapi_warning'],
                    'data' => $data,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'MT5 credentials saved and connected to broker successfully!',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function dismissPopup(Request $request, Mt5BrokerService $service)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        try {
            $data = $service->dismissPopup((string) $user['uid']);
            return response()->json([
                'success' => true,
                'message' => 'Broker popup dismissed.',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function dismissAppPopup(Request $request, Mt5BrokerService $service)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        try {
            $data = $service->dismissAppPopup((string) $user['uid']);
            return response()->json([
                'success' => true,
                'message' => 'App popup dismissed.',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function dismissDowngradeWarning(Request $request, Mt5BrokerService $service)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        try {
            $service->dismissDowngradeWarning((string) $user['uid']);
            return response()->json([
                'success' => true,
                'message' => 'Plan downgrade warning dismissed.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
