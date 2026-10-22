<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminSupportController extends Controller
{
    private function findTicket($id)
    {
        if (!$id) return null;

        try {
            $ticket = SupportTicket::where('_id', (string) $id)->first();
            if ($ticket) return $ticket;
        } catch (\Throwable $e) {
            // Ignore invalid ObjectId error
        }

        try {
            $ticket = SupportTicket::where('ticket_uid', (string) $id)->first();
            if ($ticket) return $ticket;
        } catch (\Throwable $e) {
            // Ignore
        }

        return null;
    }

    public function index()
    {
        return view('admin.support.index');
    }

    public function getTickets()
    {
        try {
            $tickets = SupportTicket::orderBy('updated_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'tickets' => $tickets,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching tickets: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $ticket = $this->findTicket($id);

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'ticket' => $ticket,
        ]);
    }

    public function reply(Request $request, $id)
    {
        try {
            $request->validate([
                'message' => 'required|string|max:2000',
            ]);

            $ticket = $this->findTicket($id);

            if (!$ticket) {
                return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
            }

            $replies = $ticket->replies ?? [];
            if (!is_array($replies)) {
                $replies = [];
            }

            $replies[] = [
                'reply_id' => (string) Str::uuid(),
                'sender_type' => 'admin',
                'sender_name' => 'Admin',
                'message' => trim($request->message),
                'created_at' => now()->toIso8601String(),
            ];

            $ticket->replies = $replies;
            $ticket->status = 'ANSWERED';
            $ticket->save();

            return response()->json([
                'success' => true,
                'message' => 'Reply sent to user.',
                'ticket' => $ticket,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending reply: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function closeTicket($id)
    {
        try {
            $ticket = $this->findTicket($id);

            if (!$ticket) {
                return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
            }

            $ticket->status = 'CLOSED';
            $ticket->save();

            return response()->json([
                'success' => true,
                'message' => 'Ticket marked as closed.',
                'ticket' => $ticket,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error closing ticket: ' . $e->getMessage(),
            ], 500);
        }
    }
}
