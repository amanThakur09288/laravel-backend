<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactMessageController extends Controller
{
    /* ---------- Public: contact form on the website ---------- */

    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        // Honeypot: real users never see this hidden field, bots fill it.
        // Pretend it worked so the bot learns nothing.
        if ($request->filled('website')) {
            return $this->thankYouResponse();
        }

        // 1. Save first, so the message is never lost
        $contact = ContactMessage::create([
            ...$request->safe()->only(['name', 'email', 'subject', 'message']),
            'ip_address' => $request->ip(),
        ]);

        // 2. Then email the admin
        $this->notifyAdmin($contact);

        return $this->thankYouResponse();
    }

    /* ---------- Admin only ---------- */

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ContactMessage::orderByDesc('id')->get(),
        ]);
    }

    public function show(ContactMessage $contactMessage): JsonResponse
{
    return response()->json([
        'success' => true,
        'data' => $contactMessage,
    ]);
}

    public function destroy(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->delete();

        return response()->json([
            'success' => true,
            'message' => 'Message deleted successfully.',
        ]);
    }

    /* ---------- Helpers ---------- */

    private function thankYouResponse(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Thank you! Your message has been sent.',
        ], 201);
    }

    private function notifyAdmin(ContactMessage $contact): void
    {
        $receivedAt = $contact->created_at
            ->timezone('Asia/Kolkata')
            ->format('d M Y, h:i A');

        // Plain text email: user input can never inject HTML
        $body = "You received a new contact enquiry.\n\n"
            . "Name: {$contact->name}\n"
            . "Email: {$contact->email}\n"
            // . 'Company: ' . ($contact->company ?: '-') . "\n"
            . "Subject: {$contact->subject}\n"
            . "Received: {$receivedAt}\n\n"
            . "Message:\n{$contact->message}\n";

        try {
            Mail::raw($body, function ($mail) use ($contact) {
                $mail->to(config('mail.contact_admin'))
                    ->replyTo($contact->email, $contact->name) // admin can just hit Reply
                    ->subject('New enquiry: ' . $contact->subject);
            });
        } catch (Throwable $e) {
            // The message is already saved, so just log the problem
            Log::error('Contact email failed: ' . $e->getMessage());
        }
    }
}