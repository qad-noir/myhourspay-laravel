<?php

return [
    'queue' => 'billing',
    'max_attempts' => 8,
    'events' => [
        'checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed',
        'customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted',
        'customer.subscription.paused', 'customer.subscription.resumed', 'customer.subscription.trial_will_end',
        'customer.updated', 'customer.deleted', 'payment_method.automatically_updated',
        'invoice.paid', 'invoice.payment_succeeded', 'invoice.payment_failed', 'invoice.payment_action_required',
        'subscription_schedule.created', 'subscription_schedule.updated', 'subscription_schedule.canceled',
        'subscription_schedule.released', 'subscription_schedule.completed', 'subscription_schedule.aborted',
        'refund.created', 'refund.updated', 'refund.failed', 'charge.refunded',
        'charge.dispute.created', 'charge.dispute.updated', 'charge.dispute.closed',
        'charge.dispute.funds_withdrawn', 'charge.dispute.funds_reinstated',
    ],
];
