<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class PromptPayQrService
{
    public function buildForMobile(string $mobileNumber, float $amount): array
    {
        $normalizedMobile = $this->normalizeThaiMobile($mobileNumber);

        if (! preg_match('/^0\d{9}$/', $normalizedMobile)) {
            throw new \InvalidArgumentException('invalid_mobile');
        }

        if (! is_finite($amount) || $amount <= 0) {
            throw new \InvalidArgumentException('invalid_amount');
        }

        $payload = $this->buildPayload($normalizedMobile, $amount);

        return [
            'mobile_number' => $normalizedMobile,
            'amount' => (float) number_format($amount, 2, '.', ''),
            'payload' => $payload,
            'qr_image_url' => $this->renderSvgDataUrl($payload),
        ];
    }

    protected function normalizeThaiMobile(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';

        if (strpos($digits, '66') === 0 && strlen($digits) === 11) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }

    protected function buildPayload(string $mobileNumber, float $amount): string
    {
        $proxy = '0066'.substr($mobileNumber, 1);
        $merchantAccount = $this->field('00', 'A000000677010111')
            .$this->field('01', $proxy);

        $amountText = number_format($amount, 2, '.', '');
        $base = $this->field('00', '01')
            .$this->field('01', '12')
            .$this->field('29', $merchantAccount)
            .$this->field('58', 'TH')
            .$this->field('53', '764')
            .$this->field('54', $amountText)
            .'6304';

        return $base.$this->crc16($base);
    }

    protected function field(string $id, string $value): string
    {
        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    protected function crc16(string $value): string
    {
        $crc = 0xFFFF;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $crc ^= ord($value[$i]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    protected function renderSvgDataUrl(string $payload): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(320, 2),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        $svg = $writer->writeString($payload);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
