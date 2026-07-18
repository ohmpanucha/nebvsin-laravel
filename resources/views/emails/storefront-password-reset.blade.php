<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $locale === 'th' ? 'รีเซ็ตรหัสผ่าน' : 'Reset your password' }}</title>
</head>
<body style="margin:0;padding:32px;background:#090909;color:#f4f4f4;font-family:Arial,sans-serif;">
    <div style="max-width:560px;margin:0 auto;border:1px solid #333;padding:32px;background:#111;">
        <p style="margin:0 0 24px;letter-spacing:0.16em;color:#aaa;">NEBVSIN</p>
        <h1 style="margin:0 0 20px;font-size:28px;">
            {{ $locale === 'th' ? 'รีเซ็ตรหัสผ่าน' : 'Reset your password' }}
        </h1>
        <p style="line-height:1.7;color:#ccc;">
            @if ($locale === 'th')
                สวัสดี {{ $displayName !== '' ? $displayName : 'คุณ' }} กดปุ่มด้านล่างเพื่อตั้งรหัสผ่านใหม่ ลิงก์นี้มีอายุ {{ $expireMinutes }} นาที
            @else
                Hello {{ $displayName !== '' ? $displayName : 'there' }}, use the button below to set a new password. This link expires in {{ $expireMinutes }} minutes.
            @endif
        </p>
        <p style="margin:28px 0;">
            <a href="{{ $resetUrl }}" style="display:inline-block;padding:13px 18px;background:#eee;color:#080808;text-decoration:none;font-weight:bold;letter-spacing:0.08em;">
                {{ $locale === 'th' ? 'ตั้งรหัสผ่านใหม่' : 'RESET PASSWORD' }}
            </a>
        </p>
        <p style="line-height:1.6;color:#888;font-size:13px;">
            {{ $locale === 'th' ? 'หากคุณไม่ได้ขอรีเซ็ตรหัสผ่าน สามารถเพิกเฉยต่ออีเมลนี้ได้' : 'If you did not request a password reset, you can ignore this email.' }}
        </p>
    </div>
</body>
</html>
