<?php

declare(strict_types=1);

namespace VprideBackend;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Computes estimated fare and best single promotion (rider-side discount only).
 */
final class FarePromoService
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array{
     *   estimated_fare: float,
     *   promo_discount: float,
     *   final_fare: float,
     *   currency: string,
     *   decimal_places: int,
     *   applied_promotion_id: ?int,
     *   promo_code_used: ?string,
     *   reward_grant_id: ?int
     * }
     */
    public function computeForNewRide(
        int $riderUserId,
        ?string $couponCodeRaw,
        ?float $baseFareOverride = null,
        bool $skipLoyaltyGrants = false,
    ): array {
        $settings = (new PlatformPromoSettingsRepository($this->pdo))->getSettings();
        $decimals = $settings['decimal_places'];
        $base = $baseFareOverride !== null
            ? $this->roundMoney((float) $baseFareOverride, $decimals)
            : $this->roundMoney((float) $settings['default_ride_estimate'], $decimals);
        $currency = $settings['currency_code'];

        if (! PromotionRepository::tableExists($this->pdo)) {
            return [
                'estimated_fare' => $base,
                'promo_discount' => 0.0,
                'final_fare' => $base,
                'currency' => $currency,
                'decimal_places' => $decimals,
                'applied_promotion_id' => null,
                'promo_code_used' => null,
                'reward_grant_id' => null,
            ];
        }

        $promoRepo = new PromotionRepository($this->pdo);
        $paidTrips = (new RiderLoyaltyRepository($this->pdo))->getPaidTripsCount($riderUserId);
        $isNewUser = $paidTrips === 0;

        $tz = new DateTimeZone($settings['promo_timezone']);
        $now = new DateTimeImmutable('now', $tz);

        $candidates = [];

        $grantRepo = new RiderRewardGrantRepository($this->pdo);
        if (! $skipLoyaltyGrants) {
            foreach ($grantRepo->listAvailableGrantsWithPromotions($riderUserId) as $g) {
                $p = $g['promotion'];
                $d = $this->evaluatePromotionRow($p, $base, $now, $isNewUser, $riderUserId, $promoRepo, null, true);
                if ($d !== null && $d['discount'] > 0) {
                    $candidates[] = [
                        'discount' => $d['discount'],
                        'promotion_id' => (int) $p['id'],
                        'code' => null,
                        'grant_id' => $g['grant_id'],
                    ];
                }
            }
        }

        $code = $couponCodeRaw !== null ? trim($couponCodeRaw) : '';
        if ($code !== '') {
            $row = $promoRepo->findByCouponCode($code);
            if ($row !== null) {
                $d = $this->evaluatePromotionRow($row, $base, $now, $isNewUser, $riderUserId, $promoRepo, $code, false);
                if ($d !== null && $d['discount'] > 0) {
                    $candidates[] = [
                        'discount' => $d['discount'],
                        'promotion_id' => (int) $row['id'],
                        'code' => strtoupper($code),
                        'grant_id' => null,
                    ];
                }
            }
        }

        $bestAutoDiscount = 0.0;
        $bestAutoPromoId = null;
        foreach ($promoRepo->listActiveAutomatic() as $row) {
            $d = $this->evaluatePromotionRow($row, $base, $now, $isNewUser, $riderUserId, $promoRepo, null, false);
            if ($d !== null && $d['discount'] > $bestAutoDiscount) {
                $bestAutoDiscount = $d['discount'];
                $bestAutoPromoId = (int) $row['id'];
            }
        }
        if ($bestAutoDiscount > 0 && $bestAutoPromoId !== null) {
            $candidates[] = [
                'discount' => $bestAutoDiscount,
                'promotion_id' => $bestAutoPromoId,
                'code' => null,
                'grant_id' => null,
            ];
        }

        $chosen = null;
        foreach ($candidates as $c) {
            if ($chosen === null || $c['discount'] > $chosen['discount']) {
                $chosen = $c;
            }
        }

        $discount = 0.0;
        $appliedId = null;
        $codeUsed = null;
        $grantId = null;
        if ($chosen !== null) {
            $discount = min($base, $this->roundMoney($chosen['discount'], $decimals));
            $appliedId = $chosen['promotion_id'];
            $codeUsed = $chosen['code'];
            $grantId = $chosen['grant_id'];
        }

        $final = $this->roundMoney(max(0.0, $base - $discount), $decimals);

        return [
            'estimated_fare' => $base,
            'promo_discount' => $discount,
            'final_fare' => $final,
            'currency' => $currency,
            'decimal_places' => $decimals,
            'applied_promotion_id' => $appliedId,
            'promo_code_used' => $codeUsed,
            'reward_grant_id' => $grantId,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function evaluatePromotionRow(
        array $row,
        float $baseFare,
        DateTimeImmutable $nowTz,
        bool $isNewUser,
        int $riderUserId,
        PromotionRepository $promoRepo,
        ?string $couponCode,
        bool $fromLoyaltyGrant,
    ): ?array {
        if (empty($row['is_active'])) {
            return null;
        }
        $starts = $row['starts_at'] ?? null;
        $ends = $row['ends_at'] ?? null;
        if ($starts !== null && $starts !== '') {
            $s = new DateTimeImmutable((string) $starts, $nowTz->getTimezone());
            if ($nowTz < $s) {
                return null;
            }
        }
        if ($ends !== null && $ends !== '') {
            $e = new DateTimeImmutable((string) $ends, $nowTz->getTimezone());
            if ($nowTz > $e) {
                return null;
            }
        }
        if (! empty($row['new_users_only']) && ! $isNewUser) {
            return null;
        }
        $minFare = $row['min_fare_amount'] ?? null;
        if ($minFare !== null && $minFare !== '' && $baseFare < (float) $minFare) {
            return null;
        }
        $kind = (string) $row['kind'];
        if ($kind === 'coupon') {
            if (! $fromLoyaltyGrant) {
                if ($couponCode === null) {
                    return null;
                }
                $dbCode = isset($row['coupon_code']) ? strtoupper(trim((string) $row['coupon_code'])) : '';
                if ($dbCode !== strtoupper(trim($couponCode))) {
                    return null;
                }
            }
        } elseif ($kind === 'automatic') {
            if (! $this->matchesSchedule($row['schedule_json'] ?? null, $nowTz)) {
                return null;
            }
        } else {
            return null;
        }

        $maxUses = $row['max_uses_per_rider'] ?? null;
        if ($maxUses !== null && $maxUses !== '') {
            $used = $promoRepo->countRedemptionsForRider((int) $row['id'], $riderUserId);
            if ($used >= (int) $maxUses) {
                return null;
            }
        }

        $discount = $this->rawDiscountForRow($row, $baseFare);
        if ($discount <= 0) {
            return null;
        }

        return ['discount' => $discount];
    }

    private function rawDiscountForRow(array $row, float $baseFare): float
    {
        $kind = (string) ($row['discount_kind'] ?? 'percent');
        $val = (float) ($row['discount_value'] ?? 0);
        if ($kind === 'percent') {
            if ($val <= 0 || $val > 100) {
                return 0.0;
            }
            $d = $baseFare * ($val / 100.0);
            $cap = $row['max_discount_amount'] ?? null;
            if ($cap !== null && $cap !== '' && (float) $cap > 0) {
                $d = min($d, (float) $cap);
            }

            return $d;
        }
        if ($kind === 'fixed_amount') {
            return $val > 0 ? min($baseFare, $val) : 0.0;
        }

        return 0.0;
    }

    private function matchesSchedule(mixed $scheduleJson, DateTimeImmutable $nowTz): bool
    {
        if ($scheduleJson === null || $scheduleJson === '') {
            return true;
        }
        if (is_string($scheduleJson)) {
            try {
                /** @var mixed $dec */
                $dec = json_decode($scheduleJson, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                return false;
            }
            $scheduleJson = $dec;
        }
        if (! is_array($scheduleJson) || $scheduleJson === []) {
            return true;
        }
        $dow = (int) $nowTz->format('N');
        $minutes = (int) $nowTz->format('G') * 60 + (int) $nowTz->format('i');
        foreach ($scheduleJson as $slot) {
            if (! is_array($slot)) {
                continue;
            }
            $d = isset($slot['dow']) ? (int) $slot['dow'] : null;
            if ($d === null || $d < 1 || $d > 7 || $d !== $dow) {
                continue;
            }
            $start = $this->parseHHMM((string) ($slot['start'] ?? '00:00'));
            $end = $this->parseHHMM((string) ($slot['end'] ?? '23:59'));
            if ($start === null || $end === null) {
                continue;
            }
            if ($end > $start && $minutes >= $start && $minutes <= $end) {
                return true;
            }
            if ($end <= $start && ($minutes >= $start || $minutes <= $end)) {
                return true;
            }
        }

        return false;
    }

    private function parseHHMM(string $s): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', trim($s), $m)) {
            return null;
        }
        $h = (int) $m[1];
        $mi = (int) $m[2];
        if ($h > 23 || $mi > 59) {
            return null;
        }

        return $h * 60 + $mi;
    }

    private function roundMoney(float $x, int $decimals): float
    {
        return round($x, $decimals, PHP_ROUND_HALF_UP);
    }
}
