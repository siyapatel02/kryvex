<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    private function getAuthUser(Request $request)
    {
        $raw = $request->attributes->get('auth_user')
            ?? $request->attributes->get('user')
            ?? (auth()->check() ? auth()->user() : null);

        if (!$raw) {
            return null;
        }

        if (is_array($raw)) {
            return (object) [
                'uid' => $raw['uid'] ?? $raw['_id'] ?? null,
                'name' => $raw['name'] ?? $raw['full_name'] ?? $raw['username'] ?? ($raw['email'] ?? 'User'),
                'email' => $raw['email'] ?? '',
            ];
        }

        return (object) [
            'uid' => $raw->uid ?? $raw->_id ?? null,
            'name' => $raw->name ?? $raw->full_name ?? $raw->username ?? ($raw->email ?? 'User'),
            'email' => $raw->email ?? '',
        ];
    }

    private function findUserTicket($id, $userUid)
    {
        if (!$id) return null;

        try {
            $ticket = SupportTicket::where('_id', (string) $id)->where('user_uid', $userUid)->first();
            if ($ticket) return $ticket;
        } catch (\Throwable $e) {
            // Ignore
        }

        try {
            $ticket = SupportTicket::where('ticket_uid', (string) $id)->where('user_uid', $userUid)->first();
            if ($ticket) return $ticket;
        } catch (\Throwable $e) {
            // Ignore
        }

        return null;
    }

    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser($request);
            if (!$user || !$user->uid) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $tickets = SupportTicket::where('user_uid', $user->uid)
                ->orderBy('updated_at', 'desc')
                ->get();

            $openCount = SupportTicket::where('user_uid', $user->uid)
                ->whereIn('status', ['OPEN', 'ANSWERED'])
                ->count();

            $totalCount = $tickets->count();

            return response()->json([
                'success' => true,
                'open_count' => $openCount,
                'total_count' => $totalCount,
                'tickets' => $tickets,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading tickets: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $this->getAuthUser($request);
            if (!$user || !$user->uid) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $request->validate([
                'subject' => 'required|string|max:255',
                'description' => 'required|string|max:2000',
                'topic' => 'nullable|string|max:100',
            ]);

            $ticketCount = SupportTicket::count();
            $ticketUid = '#' . ($ticketCount + 1);

            $initialReply = [
                'reply_id' => (string) Str::uuid(),
                'sender_type' => 'user',
                'sender_name' => $user->name ?? $user->email ?? 'You',
                'message' => trim($request->description),
                'created_at' => now()->toIso8601String(),
            ];

            $ticket = SupportTicket::create([
                'ticket_uid' => $ticketUid,
                'user_uid' => $user->uid,
                'user_name' => $user->name ?? 'User',
                'user_email' => $user->email ?? '',
                'topic' => $request->topic ?? 'Other',
                'subject' => trim($request->subject),
                'description' => trim($request->description),
                'status' => 'OPEN',
                'replies' => [$initialReply],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Support ticket created successfully.',
                'ticket' => $ticket,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reply(Request $request, $id)
    {
        try {
            $user = $this->getAuthUser($request);
            if (!$user || !$user->uid) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $request->validate([
                'message' => 'required|string|max:2000',
            ]);

            $ticket = $this->findUserTicket($id, $user->uid);

            if (!$ticket) {
                return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
            }

            $replies = $ticket->replies ?? [];
            if (!is_array($replies)) {
                $replies = [];
            }

            $replies[] = [
                'reply_id' => (string) Str::uuid(),
                'sender_type' => 'user',
                'sender_name' => $user->name ?? $user->email ?? 'You',
                'message' => trim($request->message),
                'created_at' => now()->toIso8601String(),
            ];

            $ticket->replies = $replies;
            $ticket->status = 'OPEN';
            $ticket->save();

            return response()->json([
                'success' => true,
                'message' => 'Reply sent successfully.',
                'ticket' => $ticket,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending reply: ' . $e->getMessage()
            ], 500);
        }
    }
}
