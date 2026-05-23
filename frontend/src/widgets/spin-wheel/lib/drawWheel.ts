/** Обрезает текст с многоточием, если не помещается в maxWidth. */
export function truncateText(
    ctx: CanvasRenderingContext2D,
    text: string,
    maxWidth: number
): string {
    if (maxWidth <= 0) return '';
    const width = ctx.measureText(text).width;
    if (width <= maxWidth) return text;
    const ellipsis = '...';
    const ellipsisWidth = ctx.measureText(ellipsis).width;
    let truncated = text;
    while (truncated.length > 0 && ctx.measureText(truncated + ellipsis).width > maxWidth) {
        truncated = truncated.slice(0, -1);
    }
    return truncated + ellipsis;
}

const SOFT_COLORS = [
    '#f8bbd0', '#e1bee7', '#c5cae9', '#b3e5fc', '#b2dfdb',
    '#c8e6c9', '#dcedc8', '#f0f4c3', '#fff9c4', '#ffecb3',
    '#ffe0b2', '#ffccbc', '#d7ccc8', '#cfd8dc',
    '#f48fb1', '#ce93d8', '#9fa8da', '#90caf9', '#80cbc4',
    '#a5d6a7', '#c5e1a5', '#e6ee9c', '#fff59d', '#ffe082',
    '#ffcc80', '#ffab91',
];

export function getSoftColors(count: number): string[] {
    const shuffled = [...SOFT_COLORS].sort(() => Math.random() - 0.5);
    const result: string[] = [];
    for (let i = 0; i < count; i++) {
        result.push(shuffled[i % shuffled.length] ?? '#f3f4f6');
    }
    return result;
}

export function drawWheel(
    ctx: CanvasRenderingContext2D,
    options: string[],
    colors?: string[],
    size = 400,
    segmentWeights?: number[]
) {
    if (options.length === 0) return;

    const center = size / 2;
    const radius = size / 2;
    const weights =
        segmentWeights?.length === options.length
            ? segmentWeights.map((w) => Math.max(0, Number.isFinite(w) ? w : 0))
            : options.map(() => 1);
    const totalWeight = weights.reduce((sum, w) => sum + w, 0);
    if (totalWeight <= 0) return;

    const segmentColors =
        colors?.length === options.length ? colors : getSoftColors(options.length);
    const fontSize = Math.max(12, Math.round(size * 0.04));
    const textRadius = radius * 0.9;
    const maxByRadius = textRadius * 0.92;

    let startAngle = 0;
    for (let i = 0; i < options.length; i++) {
        const weight = weights[i] ?? 0;
        if (weight <= 0.001) continue;

        const arc = (2 * Math.PI * weight) / totalWeight;
        const endAngle = startAngle + arc;

        ctx.beginPath();
        ctx.fillStyle = segmentColors[i] ?? '#f3f4f6';
        ctx.moveTo(center, center);
        ctx.arc(center, center, radius, startAngle, endAngle);
        ctx.fill();

        ctx.save();
        ctx.translate(center, center);
        ctx.fillStyle = '#000';
        ctx.font = `${fontSize}px sans-serif`;

        const maxByArc = Math.max(20, textRadius * arc * 0.85);
        const maxTextWidth = Math.min(maxByRadius, maxByArc);
        const label = truncateText(
            ctx,
            options[i] ?? '',
            options.length === 1 ? radius * 1.55 : maxTextWidth
        );

        if (options.length === 1 || arc >= Math.PI * 1.75) {
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(label, 0, 0);
        } else if (arc > 0.08) {
            ctx.rotate(startAngle + arc / 2);
            ctx.textAlign = 'right';
            ctx.textBaseline = 'alphabetic';
            ctx.fillText(label, textRadius, fontSize * 0.6);
        }
        ctx.restore();

        startAngle = endAngle;
    }
}
