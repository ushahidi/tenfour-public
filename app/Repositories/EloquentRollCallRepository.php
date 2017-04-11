<?php
namespace RollCall\Repositories;

use RollCall\Models\RollCall;
use RollCall\Models\Reply;
use RollCall\Contracts\Repositories\RollCallRepository;
use RollCall\Contracts\Repositories\OrganizationRepository;
use DB;

use Illuminate\Support\Facades\Notification;
use RollCall\Notifications\RollCallReceived;

class EloquentRollCallRepository implements RollCallRepository
{
    public function __construct(OrganizationRepository $organizations)
    {
        $this->organizations = $organizations;
    }

    public function all($org_id = null, $user_id = null, $recipient_id = null, $offset = 0, $limit = 0)
    {
        $query = RollCall::query()
          ->orderBy('created_at', 'desc')
          ->with(['replies' => function ($query) {
            // Just get the most recent replies for each user
            $query->where('replies.created_at', DB::raw("(SELECT max(`r2`.`created_at`) FROM `replies` AS r2 WHERE `r2`.`user_id` = `replies`.`user_id` AND `r2`.`roll_call_id` = `replies`.`roll_call_id`)"));
          }]);

        if ($limit > 0) {
          $query
            ->offset($offset)
            ->limit($limit);
        }

        if ($org_id) {
            $query->where('organization_id', $org_id);
        }

        if ($user_id) {
            $query->where('user_id', $user_id);
        }

        if ($recipient_id) {
            $query->whereHas('recipients', function ($query) use ($recipient_id) {
                $query->where('user_id', $recipient_id);
            });

            // Return Rollcalls owned by the receipient as well
            $query->orWhere('user_id', $recipient_id);

            //Return only the user's self test
            $query->orwhere('self_test_roll_call', 1);
        }

        $roll_calls = $query->get()->toArray();

        // Add reply and sent counts
        foreach($roll_calls as &$roll_call)
        {
            $roll_call += $this->getCounts($roll_call['id']);
        }

        return $roll_calls;
    }

    public function find($id)
    {
        $roll_call = RollCall::query()
            ->with(['replies' => function ($query) {
                // Just get the most recent replies for each user
                $query->where('replies.created_at', DB::raw("(SELECT max(`r2`.`created_at`) FROM `replies` AS r2 WHERE `r2`.`user_id` = `replies`.`user_id` AND `r2`.`roll_call_id` = `replies`.`roll_call_id`)"));
                $query->with('user');
            }])
            ->findOrFail($id)
            ->toArray();

        return $roll_call + $this->getCounts($roll_call['id']);
    }

    public function create(array $input)
    {
        $roll_call = RollCall::create($input);

        $userIds = collect($input['recipients'])->pluck('id')->all();
        $roll_call->recipients()->sync($userIds);

        if (!$roll_call['self_test_roll_call']) {
            $this->notifyRollCall($roll_call);
        }

        return $roll_call->fresh()
            ->toArray();
    }

    protected function notifyRollCall($roll_call) {
        $channels = $this->organizations->getSetting($roll_call['organization_id'], 'channels');

        if (isset($channels->slack) && isset($channels->slack->enabled)) {
            $roll_call->_slack_webhook_url = $channels->slack->webhook_url;
        }

        Notification::send($roll_call->recipients, new RollCallReceived($roll_call));
        Notification::send($roll_call, new RollCallReceived($roll_call));
    }

    public function update(array $input, $id)
    {
        $input = array_only($input, ['status', 'sent']);

        $roll_call = RollCall::findorFail($id);
        $roll_call->sent = $input['sent'];
        $roll_call->status = $input['status'];
        $roll_call->save();

        if (isset($input['recipients'])) {
            $userIds = collect($input['recipients'])->pluck('id')->all();
            $roll_call->recipients()->sync($userIds);
        }

        return $roll_call->fresh()->toArray();
    }

    public function getMessages($id, $user_id = null, $contact_id = null)
    {
        $query = RollCall::findOrFail($id)->messages()->with('user');

        if ($user_id) {
            $query->where('user_id', $user_id);
        }

        return $query->get()->toArray();
    }

    public function updateRecipientStatus($roll_call_id, $user_id, $status)
    {
        DB::table('roll_call_recipients')
            ->where('roll_call_id', '=', $roll_call_id)
            ->where('user_id', '=', $user_id)
            ->update(['response_status' => $status]);
    }

    public function getLastSentMessageId($contact_id = null)
    {
        $query = DB::table('roll_call_messages')
               ->select('roll_call_id');

        if ($contact_id) {
            $query->where('contact_id', $contact_id);
        }

        return $query->orderBy('roll_call_id', 'desc')->take(1)->value('roll_call_id');
    }

    public function getSentRollCallId($contact_id, $roll_call_id)
    {
        $query = DB::table('roll_call_messages')
               ->select('roll_call_id');

        $query->where('contact_id', $contact_id);
        $query->where('roll_call_id', $roll_call_id);

        return $query->orderBy('roll_call_id', 'desc')->take(1)->value('roll_call_id');
    }

    public function getRecipient($id, $recipient_id)
    {
        return RollCall::findOrFail($id)->recipients()
            ->where('user_id', '=', $recipient_id)
            ->get()
            ->first()
            ->toArray();
    }

    public function getRecipients($id, $unresponsive=null)
    {
        return RollCall::findOrFail($id)->recipients()
               ->get()
               ->toArray();
    }

    public function getLastUnrepliedByContact($contact_id)
    {
        return DB::table('roll_call_messages')
            ->leftJoin('replies', function ($join) {
                $join->on('roll_call_messages.roll_call_id', '=', 'replies.roll_call_id');
            })
            ->where('roll_call_messages.contact_id', '=', $contact_id)
            ->whereNull('replies.id')
            ->orderBy('roll_call_messages.roll_call_id', 'desc')
            ->take(1)
            ->value('roll_call_messages.roll_call_id');
    }

    public function getLastUnrepliedByUser($user_id)
    {
        return DB::table('roll_call_messages')
            ->leftJoin('contacts', function ($join) {
                $join->on('roll_call_messages.contact_id', '=', 'contacts.id');
            })
            ->leftJoin('replies', function ($join) {
                $join->on('replies.roll_call_id', '=', 'roll_call_messages.roll_call_id');
            })
            ->where('contacts.user_id', '=', $user_id)
            ->whereNull('replies.id')
            ->orderBy('roll_call_messages.roll_call_id', 'desc')
            ->take(1)
            ->value('roll_call_messages.roll_call_id');
    }

    public function addMessage($id, $contact_id)
    {
        $roll_call = RollCall::findorFail($id);

        $roll_call->messages()->attach($contact_id);

        return RollCall::findorFail($id)
            ->messages()
            ->where('contact_id', $contact_id)
            ->get()
            ->toArray();
    }

    public function delete($id)
    {
        //
    }

    protected function getReplyCounts($id)
    {
        return Reply::where('roll_call_id', $id)
            ->count(DB::raw('DISTINCT `user_id`')); // only count each user once
    }

    protected function getSentCounts($id)
    {
        return DB::table('roll_call_messages')
            ->where('roll_call_id', $id)
            ->count();
    }

    public function getCounts($rollCallId)
    {
        return [
            'reply_count' => $this->getReplyCounts($rollCallId),
            'sent_count' => $this->getSentCounts($rollCallId)
        ];
    }

    public function setComplaintCount($count, $id)
    {
        $roll_call = RollCall::findOrFail($id);
        $roll_call->complaint_count = $count;
        $roll_call->save();

        return $roll_call->fresh()->toArray();
    }

    public function getComplaintCountByOrg($org_id)
    {
        return RollCall::where('organization_id', $org_id)
            ->sum('complaint_count');
    }
}
