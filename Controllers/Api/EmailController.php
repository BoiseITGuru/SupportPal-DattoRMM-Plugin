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
         * Look up department ticket number format
         * Map Priorites
         * Map Status
         * Support Email Aliases (Need to create plugin first)
         */

        // //save POST Data to file for Dev review
        // file_put_contents('/var/www/supportpal/app/Plugins/DattoRMM/Controllers/Api/json_array.json', json_encode($request->input()));


        //Get POST Data
        $email = $request->input('email');
        $subject = $request->input('subject');
        $status = $request->input('status');
        $priority = $request->input('priority');
        $description = $request->input('description');
        $brand_id = $request->input('custom_fields.brand');
        $department_id = $request->input('custom_fields.department');
        $source = $request->input('source');

        //Match Statuses
        switch ($status) {
            case 2:
                $status = 1;
                break;
            case 3:
                $status = 1;
                break;
            case 4:
                $status = 2;
                break;
            case 5:
                $status = 2;
                break;
            default:
                $status = 1;
        }

        //Check if user exists
        $whereCondition = [
            ['email', '=', $email],
            ['brand_id', '=', $brand_id]
        ];
        $user = User::where($whereCondition)->first();

        if (!$user) {
            //Get org by domain if exists
            $domain = substr($email, strpos($email, '@') + 1);
            $org = UserOrganisationDomain::where('domain', $domain)->first();

            //Create User
            if ($org) {
                $user = User::create([
                    'brand_id'          => $brand_id,
                    'email'             => $email,
                    'organisation_id'   => $org->organisation_id
                ]);
            } else {
                $user = User::create([
                    'brand_id'          => $brand_id,
                    'email'             => $email
                ]);
            }
        }

        //Get Department Defualt Email
        $department_email = DepartmentEmail::where('department_id', $department_id)->first();
        $department_email_id = $department_email->id;

        //Create Ticket
        $digits = 7;
        $ticket = Ticket::create([
            'number'                => rand(pow(10, $digits-1), pow(10, $digits)-1),
            'channel_id'            => 3,
            'user_id'               => $user->id,
            'department_id'         => $department_id,
            'department_email_id'   => $department_email_id,
            'brand_id'              => $brand_id,
            'status_id'             => $status,
            'priority_id'           => $priority,
            'subject'               => $subject,
            'text'                  => $description
        ]);

        //Check if users name exist and assign to email if not
        if ($ticket->user->formatted_name) {
            $user_name = $ticket->user->formatted_name;
        } else {
            $user_name = $user->email;
        }

        //Create ticket message
        Message::create([
            'ticket_id'     => $ticket->id,
            'channel_id'    => 3,
            'user_id'       => $user->id,
            'user_name'     => $user_name,
            'by'            => 1,
            'type'          => 0,
            'excerpt'       => mb_strimwidth($ticket->subject, 0, 20, "..."),
            'text'          => $description,
            'purified_text' => $description
        ]);

        event(new TicketCreated($ticket));

        //send ticket created email
        Mailer::sendTicketMail(3,$ticket);

        return $ticket;
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
