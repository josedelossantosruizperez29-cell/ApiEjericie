<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\EquipmentRequest;
use App\Http\Requests\PreferenceRequest;
use App\Http\Requests\ProfileRequest;
use App\Http\Resources\ApiResult;
use App\Models\User;
use App\Services\GuestIdentity;
use App\Services\ProfileEditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController
{
    public function create(ProfileRequest $request, GuestIdentity $identity, ProfileEditor $editor)
    {
        $data = DB::transaction(function () use ($request, $identity, $editor) {
            $user = $editor->update(User::create(['name' => $request->validated('name', 'Invitado')]), $request->validated());

            return ['public_id' => $user->public_id, 'token' => $identity->issue($user)];
        });

        return (new ApiResult($data))->response()->setStatusCode(201)->header('Cache-Control', 'no-store');
    }

    public function rotate(Request $r, GuestIdentity $identity)
    {
        return (new ApiResult(['public_id' => $r->user()->public_id, 'token' => $identity->rotate($r->user(), $r->bearerToken())]))->response()->header('Cache-Control', 'no-store');
    }

    public function profile(Request $r)
    {
        return new ApiResult($r->user()->load('userProfile', 'userGoals', 'availableEquipment')->only(['public_id', 'name', 'last_name', 'userProfile', 'userGoals', 'availableEquipment']));
    }

    public function update(ProfileRequest $r, ProfileEditor $editor)
    {
        $editor->update($r->user(), $r->validated());

        return $this->profile($r);
    }

    public function onboarding(Request $r)
    {
        return new ApiResult($r->user()->userOnboarding);
    }

    public function onboard(ProfileRequest $r, ProfileEditor $editor)
    {
        return new ApiResult($editor->update($r->user(), $r->validated())->userOnboarding);
    }

    public function equipment(EquipmentRequest $r, ProfileEditor $editor)
    {
        return new ApiResult($editor->update($r->user(), $r->validated())->availableEquipment);
    }

    public function preferences(Request $r)
    {
        return new ApiResult($r->user()->userPreference);
    }

    public function savePreferences(PreferenceRequest $r, ProfileEditor $editor)
    {
        return new ApiResult($editor->preferences($r->user(), $r->validated()));
    }
}
