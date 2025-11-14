<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Conversation;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $this->getCompanyId();
        $messages = Message::where('company_id', $companyId)->paginate(10);
        
        return $this->paginated($messages->items(), [
            'page' => $messages->currentPage(),
            'limit' => $messages->perPage(),
            'total' => $messages->total(),
            'totalPages' => $messages->lastPage(),
        ]);
    }

    public function send(Request $request)
    {
        // TODO: Implement message sending (integrate with WhatsHub)
        return $this->error('Not implemented', null, 501);
    }

    public function threads(Request $request)
    {
        $companyId = $this->getCompanyId();
        $threads = Conversation::where('company_id', $companyId)
            ->with('participant')
            ->orderBy('last_message_at', 'desc')
            ->paginate(10);
        
        return $this->paginated($threads->items(), [
            'page' => $threads->currentPage(),
            'limit' => $threads->perPage(),
            'total' => $threads->total(),
            'totalPages' => $threads->lastPage(),
        ]);
    }

    public function templates()
    {
        // Return message templates
        return $this->success([
            [
                'id' => 'quote_sent',
                'name' => 'Quote Sent',
                'content' => 'Your quote has been sent. Please review and let us know if you have any questions.',
            ],
            [
                'id' => 'job_scheduled',
                'name' => 'Job Scheduled',
                'content' => 'Your job has been scheduled. We will arrive on {date} at {time}.',
            ],
            [
                'id' => 'payment_reminder',
                'name' => 'Payment Reminder',
                'content' => 'This is a reminder that invoice #{invoice_number} is due on {due_date}.',
            ],
        ]);
    }
}

