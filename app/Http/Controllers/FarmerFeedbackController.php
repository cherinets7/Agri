<?php

namespace App\Http\Controllers;

use App\Enums\Level;
use App\Enums\Role;
use App\Models\FarmerFeedback;
use App\Models\Location;
use App\Models\User;
use App\Support\Period;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FarmerFeedbackController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $period = Period::fromRequest($request, 'last30');

        $query = FarmerFeedback::query()
            ->visibleTo($user)
            ->with(['expert', 'location.parent'])
            ->between($period->from, $period->to);

        if ($woredaId = ($user->isWoreda() ? $user->location_id : ($request->integer('woreda') ?: null))) {
            if (! $user->isKebele()) {
                $query->whereIn('location_id', Location::query()->where('parent_id', $woredaId)->select('id'));
            }
        }

        if ($rating = $request->integer('rating')) {
            $query->where('rating', $rating);
        }

        $stats = (clone $query)->selectRaw('COUNT(*) as total, AVG(rating) as avg_rating')->first();
        $feedbacks = $query->orderByDesc('feedback_date')->orderByDesc('id')->paginate(20)->withQueryString();

        $experts = $user->isKebele()
            ? collect([$user])
            : User::query()->visibleTo($user)->where('role', Role::Kebele->value)->where('is_active', true)->orderBy('name')->get();

        $qrToken = $user->isKebele() ? $user->ensureFeedbackToken() : null;

        return view('feedback.index', [
            'feedbacks' => $feedbacks,
            'period' => $period,
            'totalCount' => (int) ($stats->total ?? 0),
            'avgRating' => $stats->avg_rating !== null ? round((float) $stats->avg_rating, 2) : null,
            'woredas' => $user->isZone() ? Location::query()->ofLevel(Level::Woreda)->orderBy('name_am')->get() : collect(),
            'experts' => $experts,
            'qrToken' => $qrToken,
        ]);
    }

    public function qrCard(Request $request, ?User $expert = null)
    {
        $viewer = $request->user();
        $target = $expert ?? $viewer;

        abort_unless($target->isKebele(), 404);
        if ($viewer->isKebele()) {
            abort_unless($target->id === $viewer->id, 403);
        } elseif ($viewer->isWoreda()) {
            abort_unless($target->location?->parent_id === $viewer->location_id, 403);
        }

        $token = $target->ensureFeedbackToken();
        $url = route('feedback.public', $token);

        $writer = new Writer(new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd));
        $svg = $writer->writeString($url);

        return view('feedback.qr-card', [
            'expert' => $target,
            'url' => $url,
            'svg' => $svg,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isKebele(), 403);

        return view('feedback.form', [
            'expert' => $user,
            'services' => config('extension.service_types'),
            'publicMode' => false,
            'actionUrl' => route('feedback.store'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isKebele(), 403);

        $this->saveFeedback($request, $user, 'mobile');

        return redirect()->route('feedback.index')->with('status', 'የአርሶ አደር አስተያየት ተመዝግቧል።');
    }

    public function publicForm(string $token)
    {
        $expert = User::query()->where('feedback_token', $token)->where('role', Role::Kebele->value)->where('is_active', true)->firstOrFail();

        return view('feedback.form', [
            'expert' => $expert,
            'services' => config('extension.service_types'),
            'publicMode' => true,
            'actionUrl' => route('feedback.public.store', $token),
        ]);
    }

    public function publicStore(Request $request, string $token): RedirectResponse
    {
        $expert = User::query()->where('feedback_token', $token)->where('role', Role::Kebele->value)->where('is_active', true)->firstOrFail();

        $this->saveFeedback($request, $expert, 'qr');

        return redirect()->route('feedback.public', $token)->with('status', 'አስተያየትዎ ስለደረሰን እናመሰግናለን!');
    }

    private function saveFeedback(Request $request, User $expert, string $channel): FarmerFeedback
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'service_type' => ['nullable', Rule::in(array_keys(config('extension.service_types')))],
            'farmer_name' => ['nullable', 'string', 'max:120'],
            'farmer_phone' => ['nullable', 'string', 'max:30'],
            'comment' => ['nullable', 'string', 'max:1500'],
            'signature_data' => ['nullable', 'string', 'max:150000'],
        ], [], [
            'rating' => 'ደረጃ (1–5)',
            'service_type' => 'የአገልግሎት ዓይነት',
            'farmer_name' => 'የአርሶ አደር ስም',
            'comment' => 'አስተያየት',
        ]);

        $sig = $data['signature_data'] ?? null;
        if ($sig && ! str_starts_with($sig, 'data:image/png;base64,')) {
            $sig = null;
        }

        return FarmerFeedback::create([
            'expert_id' => $expert->id,
            'location_id' => $expert->location_id,
            'feedback_date' => today(),
            'rating' => (int) $data['rating'],
            'service_type' => $data['service_type'] ?? null,
            'farmer_name' => $data['farmer_name'] ?? null,
            'farmer_phone' => $data['farmer_phone'] ?? null,
            'comment' => $data['comment'] ?? null,
            'signature_data' => $sig,
            'channel' => $channel,
            'submitted_ip' => $request->ip(),
        ]);
    }
}
