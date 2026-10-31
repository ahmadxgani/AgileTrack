import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export type RoleOption = {
    value: string;
    label: string;
};

export default function RoleOptions({
    options,
    selected,
    error,
    onToggle,
}: {
    options: RoleOption[];
    selected: string[];
    error?: string;
    onToggle: (value: string) => void;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-2 text-sm font-medium">Roles</legend>
            <p className="mb-2 text-sm text-muted-foreground">
                Assign at least one role. Roles do not grant project access.
            </p>
            <div className="grid gap-2 sm:grid-cols-2">
                {options.map((option) => (
                    <div
                        key={option.value}
                        className="flex items-center gap-2 text-sm"
                    >
                        <Checkbox
                            id={`role-${option.value}`}
                            checked={selected.includes(option.value)}
                            onCheckedChange={() => onToggle(option.value)}
                        />
                        <Label htmlFor={`role-${option.value}`}>
                            {option.label}
                        </Label>
                    </div>
                ))}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}
