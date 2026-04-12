import { ref } from 'vue';

function mod360(deg: number): number {
    return ((deg % 360) + 360) % 360;
}

/** optionsGetter вызывается при расчёте результата, чтобы использовать актуальный список. */
export function useSpinWheel(
    optionsGetter: () => string[],
    duration = 4000
) {
    const angle = ref(0);
    const result = ref<string | null>(null);

    const spin = () => {
        const options = optionsGetter();
        if (options.length === 0) return;

        const n = options.length;
        const arc = 360 / n;
        /** Сначала случайный победитель, затем угол остановки — иначе из-за привязки к 0° визуально/по модулю 360 результат может казаться зафиксированным. */
        const winIdx = Math.floor(Math.random() * n);
        const margin = Math.min(arc * 0.06, 8);
        const span = Math.max(arc - 2 * margin, arc * 0.5);
        const norm = winIdx * arc + margin + Math.random() * span;
        /** Остаток angle % 360, при котором под стрелкой (270°) оказывается norm. */
        const remainderTarget = mod360(270 - norm);

        const startAngle = angle.value;
        const startRem = mod360(startAngle);
        const delta = mod360(remainderTarget - startRem);
        const fullTurns = 5 + Math.floor(Math.random() * 4);
        const finalAngle = startAngle + delta + 360 * fullTurns;

        const start = performance.now();

        const animate = (now: number) => {
            const progress = Math.min((now - start) / duration, 1);
            const exponent = 3 + Math.min(duration / 5000, 1);
            const ease = 1 - Math.pow(1 - progress, exponent);
            angle.value = startAngle + ease * (finalAngle - startAngle);

            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                angle.value = finalAngle;
                calculateResult();
            }
        };

        requestAnimationFrame(animate);
    };

    const calculateResult = () => {
        const options = optionsGetter();
        if (options.length === 0) {
            result.value = null;
            return;
        }
        // В canvas 270° = верх круга (12 часов). Стрелка указывает туда.
        const topAngle = 270;
        const normalizedAngle = mod360(topAngle - mod360(angle.value));
        const arc = 360 / options.length;
        const selectedIndex = Math.floor(normalizedAngle / arc) % options.length;
        result.value = options[selectedIndex];
    };

    return {
        angle,
        result,
        spin
    };
}
