<?php

namespace App\Http\Controllers;

use App\Services\ExtensionPerformance;
use App\Services\ReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('notifications.index', [
            'notifications' => $user->notifications()->paginate(20),
            'unreadCount' => $user->unreadNotifications()->count(),
            'lastWorkDay' => ExtensionPerformance::lastWorkDay(),
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $target = $notification->data['url'] ?? null;

        return $target ? redirect($target) : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'ሁሉም ማሳሰቢያዎች እንደተነበቡ ተመዝግበዋል።');
    }

    public function remindMissing(Request $request, ReminderService $reminders): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isZone() || $user->isWoreda(), 403);

        $sendEmail = $request->boolean('send_email', true);
        $res = $reminders->sendMissingReminders($user, null, $user->isZone(), $sendEmail);

        return back()->with('status', "ማሳሰቢያ ተልኳል፦ ዕለታዊ {$res['daily_sent']}፣ ሳምንታዊ {$res['weekly_sent']}።");
    }
}
