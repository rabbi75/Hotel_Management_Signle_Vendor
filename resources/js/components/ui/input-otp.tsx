import { cn } from '@/lib/utils';
import { OTPInput, OTPInputContext } from 'input-otp';
import { Dot } from 'lucide-react';
import { type ComponentProps, use } from 'react';

export function InputOTP({ className, containerClassName, ...props }: ComponentProps<typeof OTPInput>) {
    return (
        <OTPInput
            containerClassName={cn('flex items-center gap-2 has-disabled:opacity-50', containerClassName)}
            className={cn('disabled:cursor-not-allowed', className)}
            {...props}
        />
    );
}

export function InputOTPGroup({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('flex items-center', className)} {...props} />;
}

export function InputOTPSlot({ index, className, ...props }: ComponentProps<'div'> & { index: number }) {
    const inputOTPContext = use(OTPInputContext);
    const slot = inputOTPContext.slots[index];
    const char = slot?.char ?? null;
    const hasFakeCaret = slot?.hasFakeCaret ?? false;
    const isActive = slot?.isActive ?? false;

    return (
        <div
            className={cn(
                'relative flex h-9 w-9 items-center justify-center border-y border-r border-input text-sm shadow-xs transition-all outline-none first:rounded-l-md first:border-l last:rounded-r-md',
                isActive && 'z-10 border-ring ring-2 ring-ring',
                className,
            )}
            {...props}
        >
            {char}
            {hasFakeCaret && (
                <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div className="h-4 w-px animate-caret-blink bg-foreground duration-1000" />
                </div>
            )}
        </div>
    );
}

export function InputOTPSeparator({ ...props }: ComponentProps<'div'>) {
    return (
        <div role="separator" {...props}>
            <Dot />
        </div>
    );
}
