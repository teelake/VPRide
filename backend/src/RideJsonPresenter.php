<?php

declare(strict_types=1);

namespace VprideBackend;

/**
 * Normalizes a rides row for mobile JSON.
 */
final class RideJsonPresenter
{
    /**
     * @param array<string, mixed> $ride
     * @return array<string, mixed>
     */
    public static function toPublicArray(array $ride, int $decimals = 2): array
    {
        $decimals = max(0, min(4, $decimals));
        $sched = $ride['scheduled_pickup_at'] ?? null;
        $schedOut = null;
        if ($sched !== null && $sched !== '') {
            $schedOut = str_replace(' ', 'T', (string) $sched) . 'Z';
        }

        $out = [
            'id' => (int) $ride['id'],
            'status' => (string) $ride['status'],
            'riderUserId' => (int) $ride['rider_user_id'],
            'driverUserId' => isset($ride['driver_rider_user_id']) && $ride['driver_rider_user_id'] !== null
                ? (int) $ride['driver_rider_user_id']
                : null,
            'pickup' => [
                'latitude' => isset($ride['pickup_lat']) ? (float) $ride['pickup_lat'] : null,
                'longitude' => isset($ride['pickup_lng']) ? (float) $ride['pickup_lng'] : null,
                'address' => $ride['pickup_address'] !== null ? (string) $ride['pickup_address'] : null,
            ],
            'dropoff' => [
                'latitude' => isset($ride['dropoff_lat']) && $ride['dropoff_lat'] !== null
                    ? (float) $ride['dropoff_lat']
                    : null,
                'longitude' => isset($ride['dropoff_lng']) && $ride['dropoff_lng'] !== null
                    ? (float) $ride['dropoff_lng']
                    : null,
                'address' => isset($ride['dropoff_address']) && $ride['dropoff_address'] !== null
                    ? (string) $ride['dropoff_address']
                    : null,
            ],
            'scheduledPickupAt' => $schedOut,
            'tripLeg' => isset($ride['trip_leg']) ? (string) $ride['trip_leg'] : 'single',
            'companionRideId' => isset($ride['companion_ride_id']) && $ride['companion_ride_id'] !== null
                ? (int) $ride['companion_ride_id']
                : null,
            'distanceKm' => isset($ride['distance_km']) && $ride['distance_km'] !== null
                ? round((float) $ride['distance_km'], 3)
                : null,
            'ratingStars' => isset($ride['rating_stars']) && $ride['rating_stars'] !== null
                ? (int) $ride['rating_stars']
                : null,
            'feedbackText' => isset($ride['feedback_text']) && $ride['feedback_text'] !== null
                ? (string) $ride['feedback_text']
                : null,
            'ratedAt' => isset($ride['rated_at']) && $ride['rated_at'] !== null
                ? str_replace(' ', 'T', (string) $ride['rated_at']) . 'Z'
                : null,
            'pricing' => [
                'estimatedFare' => isset($ride['estimated_fare_amount']) && $ride['estimated_fare_amount'] !== null
                    ? round((float) $ride['estimated_fare_amount'], $decimals)
                    : null,
                'promoDiscount' => isset($ride['promo_discount_amount'])
                    ? round((float) $ride['promo_discount_amount'], $decimals)
                    : 0.0,
                'finalFare' => isset($ride['final_fare_amount']) && $ride['final_fare_amount'] !== null
                    ? round((float) $ride['final_fare_amount'], $decimals)
                    : null,
                'currency' => isset($ride['fare_currency']) ? (string) $ride['fare_currency'] : 'NGN',
            ],
        ];

        if (array_key_exists('payment_status', $ride)) {
            $sub = $ride['payment_submitted_at'] ?? null;
            $paid = $ride['paid_at'] ?? null;
            $out['payment'] = [
                'status' => (string) ($ride['payment_status'] ?? 'pending'),
                'method' => isset($ride['payment_method']) && $ride['payment_method'] !== null && $ride['payment_method'] !== ''
                    ? (string) $ride['payment_method']
                    : null,
                'proofUrl' => isset($ride['payment_proof_url']) && $ride['payment_proof_url'] !== null
                    ? (string) $ride['payment_proof_url']
                    : null,
                'referenceNote' => isset($ride['payment_reference_note']) && $ride['payment_reference_note'] !== null
                    ? (string) $ride['payment_reference_note']
                    : null,
                'submittedAt' => $sub !== null && $sub !== ''
                    ? str_replace(' ', 'T', (string) $sub) . 'Z'
                    : null,
                'paidAt' => $paid !== null && $paid !== ''
                    ? str_replace(' ', 'T', (string) $paid) . 'Z'
                    : null,
            ];
        }

        return $out;
    }
}
