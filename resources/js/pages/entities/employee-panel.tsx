import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DateField } from '@/components/ui/date-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type EmployeeDetails = {
    birth_date: string | null;
    children: number;
    hire_date: string | null;
    termination_date: string | null;
    prior_service_years: string;
    work_days_per_week: number;
    leave_days_override: number | null;
};

/**
 * What the calculations need to know about an employee: children and birth date
 * set the tax withheld from the salary; the hire date, earlier service and working
 * week set the leave days and the severance.
 */
export function EmployeePanel({
    entityId,
    employee,
}: {
    entityId: number;
    employee: EmployeeDetails;
}) {
    const form = useForm({
        birth_date: employee.birth_date ?? '',
        children: String(employee.children),
        hire_date: employee.hire_date ?? '',
        termination_date: employee.termination_date ?? '',
        prior_service_years: String(Number(employee.prior_service_years)),
        work_days_per_week: String(employee.work_days_per_week),
        leave_days_override:
            employee.leave_days_override != null
                ? String(employee.leave_days_override)
                : '',
    });

    function save(e: FormEvent) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            birth_date: data.birth_date || null,
            hire_date: data.hire_date || null,
            termination_date: data.termination_date || null,
            prior_service_years: data.prior_service_years.replace(',', '.'),
            leave_days_override:
                data.leave_days_override === ''
                    ? null
                    : data.leave_days_override,
        }));
        form.patch(`/entities/${entityId}/employee`, { preserveScroll: true });
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Employment</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={save} className="grid gap-4 sm:grid-cols-4">
                    <div className="grid gap-2">
                        <Label htmlFor="hire_date">Hired on</Label>
                        <DateField
                            id="hire_date"
                            value={form.data.hire_date}
                            showCalendar={false}
                            onChange={(iso) => form.setData('hire_date', iso)}
                        />
                        <InputError message={form.errors.hire_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="termination_date">Left on</Label>
                        <DateField
                            id="termination_date"
                            value={form.data.termination_date}
                            showCalendar={false}
                            onChange={(iso) =>
                                form.setData('termination_date', iso)
                            }
                        />
                        <InputError message={form.errors.termination_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="birth_date">Born on</Label>
                        <DateField
                            id="birth_date"
                            value={form.data.birth_date}
                            showCalendar={false}
                            onChange={(iso) => form.setData('birth_date', iso)}
                        />
                        <InputError message={form.errors.birth_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="children">Dependent children</Label>
                        <Input
                            id="children"
                            inputMode="numeric"
                            value={form.data.children}
                            onChange={(e) =>
                                form.setData('children', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.children} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="work_days_per_week">Working week</Label>
                        <Select
                            value={form.data.work_days_per_week}
                            onValueChange={(v) =>
                                form.setData('work_days_per_week', v)
                            }
                        >
                            <SelectTrigger id="work_days_per_week">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="5">5 days</SelectItem>
                                <SelectItem value="6">6 days</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="prior_service_years">
                            Years worked elsewhere before
                        </Label>
                        <Input
                            id="prior_service_years"
                            inputMode="decimal"
                            value={form.data.prior_service_years}
                            onChange={(e) =>
                                form.setData(
                                    'prior_service_years',
                                    e.target.value,
                                )
                            }
                            required
                        />
                        <InputError message={form.errors.prior_service_years} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="leave_days_override">
                            Leave days a year
                        </Label>
                        <Input
                            id="leave_days_override"
                            inputMode="numeric"
                            value={form.data.leave_days_override}
                            onChange={(e) =>
                                form.setData(
                                    'leave_days_override',
                                    e.target.value,
                                )
                            }
                            placeholder="By law"
                        />
                        <InputError message={form.errors.leave_days_override} />
                    </div>
                    <div className="sm:col-span-4">
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
