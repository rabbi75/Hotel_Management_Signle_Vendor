/*
|------------------------------------------------------------------------------
| Password strength
|------------------------------------------------------------------------------
|
| A deliberately small heuristic that mirrors the server's own rules
| (App\Modules\Auth\Actions\Concerns\PasswordValidationRules) rather than trying
| to be a cracking-time estimator. Its only job is to steer the user before the
| round trip; the server still decides whether a password is acceptable.
|
*/

export interface PasswordStrength {
    /** 0 (unusable) through 4 (strong). */
    score: 0 | 1 | 2 | 3 | 4;
    label: string;
    /** Semantic token suffix — `destructive`, `warning` or `success`. */
    tone: 'destructive' | 'warning' | 'success';
    /** The single most useful thing to fix next, or `null` when nothing is missing. */
    hint: string | null;
}

const LABELS: Record<PasswordStrength['score'], string> = {
    0: 'Too weak',
    1: 'Weak',
    2: 'Fair',
    3: 'Good',
    4: 'Strong',
};

export function scorePassword(value: string): PasswordStrength {
    const checks = {
        length: value.length >= 12,
        lower: /[a-z]/.test(value),
        upper: /[A-Z]/.test(value),
        digit: /\d/.test(value),
        symbol: /[^A-Za-z0-9]/.test(value),
    };

    const passed = Object.values(checks).filter(Boolean).length;
    const bonus = value.length >= 16 ? 1 : 0;
    const raw = Math.min(4, Math.max(0, passed + bonus - 1));
    const score = raw as PasswordStrength['score'];

    const hint = !checks.length
        ? 'Use at least 12 characters.'
        : !checks.lower || !checks.upper
          ? 'Mix upper and lower case letters.'
          : !checks.digit
            ? 'Add a number.'
            : !checks.symbol
              ? 'Add a symbol.'
              : null;

    return {
        score,
        label: LABELS[score],
        tone: score <= 1 ? 'destructive' : score === 2 ? 'warning' : 'success',
        hint: value === '' ? null : hint,
    };
}
