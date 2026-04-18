<?php

declare(strict_types=1);

namespace VprideBackend;

use PDO;
use Throwable;

/**
 * After a ride is marked paid: increment trip count and optionally grant loyalty rewards.
 */
final class LoyaltyRewardService
{
    public function __construct(private PDO $pdo) {}

    public function onRideMarkedPaid(int $rideId, int $riderUserId): void
    {
        if (! RiderLoyaltyRepository::tableExists($this->pdo)
            || ! PlatformPromoSettingsRepository::tableExists($this->pdo)
            || ! RiderRewardGrantRepository::tableExists($this->pdo)
            || ! PromotionRepository::tableExists($this->pdo)
        ) {
            return;
        }

        $settings = (new PlatformPromoSettingsRepository($this->pdo))->getSettings();
        if (! $settings['loyalty_enabled']) {
            return;
        }

        $rewardPromoId = $settings['loyalty_reward_promotion_id'];
        if ($rewardPromoId === null || $rewardPromoId < 1) {
            return;
        }

        $promo = (new PromotionRepository($this->pdo))->findById($rewardPromoId);
        if ($promo === null || empty($promo['is_active'])) {
            return;
        }

        $n = (new RiderLoyaltyRepository($this->pdo))->incrementPaidTrips($riderUserId);
        $every = $settings['loyalty_trips_per_reward'];
        if ($every < 1 || $n % $every !== 0) {
            return;
        }

        try {
            (new RiderRewardGrantRepository($this->pdo))->insertGrant($riderUserId, $rewardPromoId, null);
        } catch (Throwable $e) {
            error_log('[vpride] loyalty grant failed: ' . $e->getMessage());
        }
    }
}
