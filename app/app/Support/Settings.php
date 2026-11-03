<?php

namespace App\Support;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Typed allowlist of owner-editable business settings. Secrets never live here.
 * Unknown real-world details default to null and render as "not yet provided", never invented.
 */
class Settings
{
    /**
     * @var array<string, array{type: string, default: mixed, label: string}>
     */
    public const DEFINITIONS = [
        'contact_phone' => ['type' => 'string', 'default' => null, 'label' => 'Холбоо барих утас'],
        'address' => ['type' => 'string', 'default' => null, 'label' => 'Хаяг'],
        'landmark_directions' => ['type' => 'string', 'default' => null, 'label' => 'Ойролцоох тэмдэг, зогсоол'],
        'map_lat' => ['type' => 'float', 'default' => null, 'label' => 'Газрын зургийн өргөрөг'],
        'map_lng' => ['type' => 'float', 'default' => null, 'label' => 'Газрын зургийн уртраг'],
        'closure_notice' => ['type' => 'string', 'default' => null, 'label' => 'Түр хаалтын мэдэгдэл'],
        'owner_intro' => ['type' => 'string', 'default' => null, 'label' => 'Эзний / багийн танилцуулга (баталгаатай мэдээлэл)'],
        'callout_enabled' => ['type' => 'bool', 'default' => false, 'label' => 'Очиж үйлчлэх хүсэлт авах'],
        'callout_area' => ['type' => 'string', 'default' => null, 'label' => 'Очих үйлчилгээний хүрээ'],
        'callout_fee_policy' => ['type' => 'string', 'default' => null, 'label' => 'Очих үйлчилгээний төлбөрийн бодлого'],
        'quiet_start' => ['type' => 'string', 'default' => '20:00', 'label' => 'Мессеж илгээхгүй цаг эхлэх'],
        'quiet_end' => ['type' => 'string', 'default' => '09:00', 'label' => 'Мессеж илгээхгүй цаг дуусах'],
        'invoice_footer' => ['type' => 'string', 'default' => null, 'label' => 'Нэхэмжлэлийн доорх тэмдэглэл'],
        'public_domain' => ['type' => 'string', 'default' => null, 'label' => 'Нийтийн домэйн'],
        'details_approved' => ['type' => 'bool', 'default' => false, 'label' => 'Эзэн бизнесийн мэдээллийг баталсан'],
        'public_booking_enabled' => ['type' => 'bool', 'default' => true, 'label' => 'Олон нийтээс хүсэлт хүлээн авах'],
        'last_restore_test_at' => ['type' => 'string', 'default' => null, 'label' => 'Сүүлд нөөцөөс сэргээж шалгасан огноо'],
    ];

    public static function get(string $key): mixed
    {
        $definition = self::definition($key);
        $all = self::all();

        return array_key_exists($key, $all) ? $all[$key] : $definition['default'];
    }

    public static function set(string $key, mixed $value): void
    {
        $definition = self::definition($key);

        $value = match (true) {
            $value === null || $value === '' => null,
            $definition['type'] === 'bool' => (bool) $value,
            $definition['type'] === 'float' => (float) $value,
            default => trim((string) $value),
        };

        BusinessSetting::query()->updateOrCreate(['key' => $key], ['value' => ['v' => $value]]);
        Cache::forget('business_settings');
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return Cache::rememberForever('business_settings', function (): array {
            return BusinessSetting::query()->get()
                ->filter(fn (BusinessSetting $setting): bool => array_key_exists($setting->key, self::DEFINITIONS))
                ->mapWithKeys(fn (BusinessSetting $setting): array => [$setting->key => $setting->value['v'] ?? null])
                ->all();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function allWithDefaults(): array
    {
        $values = [];

        foreach (array_keys(self::DEFINITIONS) as $key) {
            $values[$key] = self::get($key);
        }

        return $values;
    }

    public static function contactPhone(): ?string
    {
        return Phone::normalize(self::get('contact_phone'));
    }

    public static function mapUrl(): ?string
    {
        $lat = self::get('map_lat');
        $lng = self::get('map_lng');

        if ($lat === null || $lng === null) {
            return null;
        }

        return sprintf('https://www.google.com/maps/search/?api=1&query=%s,%s', $lat, $lng);
    }

    /**
     * @return array{type: string, default: mixed, label: string}
     */
    private static function definition(string $key): array
    {
        if (! array_key_exists($key, self::DEFINITIONS)) {
            throw new InvalidArgumentException("Unknown setting [{$key}].");
        }

        return self::DEFINITIONS[$key];
    }
}
