<?php

namespace App\Policies;

use App\Models\MediaFile;
use App\Models\User;

/**
 * Staff: office staff see all photos; mechanics see photos of orders assigned to them.
 * Customers: only photos explicitly marked customer-visible on their own work orders.
 * Request photos are never shown to customers (the request was not verified as theirs).
 */
class MediaFilePolicy
{
    public function view(User $user, MediaFile $media): bool
    {
        if ($user->isStaff()) {
            if (! $user->active) {
                return false;
            }

            if ($user->isOfficeStaff()) {
                return true;
            }

            return $media->work_order_id !== null && $media->workOrder?->assigned_mechanic_id === $user->id;
        }

        if ($user->isCustomer()) {
            $customerId = $user->customer?->id;

            return $customerId !== null
                && $media->customer_visible
                && $media->work_order_id !== null
                && $media->workOrder?->customer_id === $customerId;
        }

        return false;
    }
}
