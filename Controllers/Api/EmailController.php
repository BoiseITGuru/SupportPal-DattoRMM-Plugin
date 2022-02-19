<?php declare(strict_types=1);

namespace App\Plugins\DattoRMM\Controllers\Api;

use App\Modules\Core\Controllers\BaseApiController;
use App\Modules\Core\Controllers\Mailer\Mailer;
use App\Modules\Core\Models\ActivityLog;
use App\Modules\Ticket\Models\Ticket;
use App\Plugins\DattoRMM\Requests\Api\Alerts\InboundEmail;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use function call_user_func;
use function count;
use function mb_strtolower;
use function mb_strimwidth;
use function trans;

class EmailController extends BaseApiController
{
    /**
     * TicketController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param InboundEmail $request
     * @return mixed
     */
    public function inboundEmail(InboundEmail $request)
    {
        /**
         * TO DO
         * 
         */

        //Get POST Data
        // $email = $request->input('email');
        

        return $request->input();
    }


    /**
     * @param int $ticketId
     * @return mixed
     */
    public function addNote(NoteRequest $request, int $ticketId)
    {
        /**
         * TO DO
         * EVERYTHING LOL
         */

        //Get POST Data
        $attachments = $request->input('attachments');
        $body = $request->input('body');
        $incoming = $request->input('incoming');
        $notify_emails = $request->input('notify_emails');
        $private = $request->input('private');
        $user_id = $request->input('user_id');
        

        //Create ticket message
        $note = Message::create([
            'ticket_id'     => $ticketId,
            'channel_id'    => 3,
            'user_id'       => $user_id,
            'user_name'     => 'Helpdesk Buttons',
            'by'            => 1,
            'type'          => 1,
            'excerpt'       => "Helpdesk Buttons Report...",
            'text'          => $body,
            'purified_text' => $body
        ]);

        return $note;
    }
}
