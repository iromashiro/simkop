<?php
// app/Http/Requests/API/Member/UpdateMemberRequest.php
namespace App\Http\Requests\API\Member;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->route('member');
        return $this->user()->can('update', $member);
    }

    public function rules(): array
    {
        $memberId = $this->route('member')->id;

        return [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('members', 'email')->ignore($memberId)
            ],
            'phone' => 'sometimes|string|max:20|regex:/^[0-9\+\-\(\)\s]+$/',
            'address' => 'sometimes|string|max:500',
            'occupation' => 'nullable|string|max:100',
            'membership_type' => 'sometimes|string|in:regular,premium,honorary',
            'status' => 'sometimes|string|in:active,inactive,suspended',
        ];
    }
}
