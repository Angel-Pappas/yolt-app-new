<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Support\PayrollCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The payroll calculation as the forms' live preview: FMY and EFKA for a gross
 * amount, an employee and a date — the very calculator the generator uses, so what
 * the form shows is what gets saved.
 */
class PayrollController extends Controller
{
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gross' => ['required', 'numeric', 'min:0'],
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'date' => ['required', 'date'],
        ]);

        $profile = isset($data['entity_id'])
            ? Entity::query()->find((int) $data['entity_id'])?->employeeProfile
            : null;

        return response()->json(PayrollCalculator::forEmployee((float) $data['gross'], $profile, (string) $data['date']));
    }
}
