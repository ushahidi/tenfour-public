<?php

namespace RollCall\Repositories;

use RollCall\Models\Subscription;
use RollCall\Models\Addon;
use RollCall\Contracts\Repositories\SubscriptionRepository;
use DB;

class EloquentSubscriptionRepository implements SubscriptionRepository
{

    public function __construct()
    {
    }

    public function all($organization_id, $offset = 0, $limit = 0)
    {
        return Subscription::where('organization_id', '=', $organization_id)->get()->toArray();
    }

    public function update($organization_id, array $input, $id)
    {
        return $this->create($organization_id, $input);
    }

    public function create($organization_id, array $input)
    {
        \Log::info($input);

        $subscription = Subscription::updateOrCreate([
            'subscription_id'   => $input['subscription']['id'],
            ],[
            'subscription_id'   => $input['subscription']['id'],
            'organization_id'   => $organization_id,
            'status'            => $input['subscription']['status'],
            'customer_id'       => $input['customer']['id'],
            'plan_id'           => $input['subscription']['plan_id'],
            'next_billing_at'   => isset($input['subscription']['current_term_end']) ? $input['subscription']['current_term_end'] : null,
            'trial_ends_at'     => isset($input['subscription']['trial_end']) ? $input['subscription']['trial_end'] : null,
            'quantity'          => $input['subscription']['plan_quantity'],
            'last_four'         => $input['card']['last4'],
            'card_type'         => ucfirst($input['card']['card_type']),
            'expiry_month'      => $input['card']['expiry_month'],
            'expiry_year'       => $input['card']['expiry_year'],
        ]);

        Addon::where('subscription_id', $input['subscription']['id'])->delete();

        if (isset($input['subscription']['addons'])) {
            foreach ($input['subscription']['addons'] as $addon)
            {
                $subscription->addons()->create([
                    'quantity' => $addon['quantity'],
                    'addon_id' => $addon['id'],
                ]);
            }
        }

        return $subscription->toArray();
    }

    public function find($organization_id, $id)
    {
        return Subscription::findOrFail($id)->toArray();;
    }

    public function delete($organization_id, $id)
    {
        Subscription::where('id', $id)->delete();
    }
}
