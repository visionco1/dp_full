<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Presenter;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class GarageController extends Controller
{
    private function mine(Request $r, Vehicle $v): Vehicle
    {
        abort_unless($v->user_id === $r->user()->id, 404);
        return $v;
    }

    public function index(Request $r)
    {
        return ['vehicles' => $r->user()->vehicles()->with('carModel.make')->orderByDesc('is_active')->orderBy('id')->get()->map(fn ($v) => Presenter::vehicle($v))];
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'car_model_id' => 'required|exists:car_models,id',
            'year' => 'required|integer|between:1980,'.(now()->year + 1),
            'engine' => 'nullable|string|max:40',
            'vin' => ['nullable', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/i'],
        ], ['vin.regex' => 'رقم الهيكل 17 خانة (بدون I و O و Q)']);
        $u = $r->user();
        $v = $u->vehicles()->create($d + ['is_active' => !$u->vehicles()->exists()]);
        return response()->json(['vehicle' => Presenter::vehicle($v->load('carModel.make'))], 201);
    }

    public function update(Request $r, Vehicle $vehicle)
    {
        $this->mine($r, $vehicle);
        $d = $r->validate(['year' => 'sometimes|integer|between:1980,'.(now()->year + 1), 'engine' => 'sometimes|nullable|string|max:40',
            'vin' => ['sometimes', 'nullable', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/i']]);
        $vehicle->update($d);
        return ['vehicle' => Presenter::vehicle($vehicle->load('carModel.make'))];
    }

    public function activate(Request $r, Vehicle $vehicle)
    {
        $this->mine($r, $vehicle);
        $r->user()->vehicles()->update(['is_active' => false]);
        $vehicle->update(['is_active' => true]);
        return ['vehicle' => Presenter::vehicle($vehicle->load('carModel.make'))];
    }

    public function destroy(Request $r, Vehicle $vehicle)
    {
        $this->mine($r, $vehicle);
        $wasActive = $vehicle->is_active;
        $vehicle->delete();
        if ($wasActive && ($next = $r->user()->vehicles()->first())) {
            $next->update(['is_active' => true]);
        }
        return response()->json(['message' => 'تم حذف السيارة من الكراج']);
    }
}
