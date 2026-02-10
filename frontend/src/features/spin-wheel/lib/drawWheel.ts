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
        result.push(shuffled[i % shuffled.length]);
    }
    return result;
}

export function drawWheel(
    ctx: CanvasRenderingContext2D,
    options: string[],
    colors?: string[],
    size = 400
) {
    const center = size / 2;
    const radius = size / 2;
    const arc = (2 * Math.PI) / options.length;
    const segmentColors =
        colors?.length === options.length ? colors : getSoftColors(options.length);
    const fontSize = Math.max(12, Math.round(size * 0.04));
    const textRadius = radius * 0.9;

    for (let i = 0; i < options.length; i++) {
        ctx.beginPath();
        ctx.fillStyle = segmentColors[i];
        ctx.moveTo(center, center);
        ctx.arc(center, center, radius, arc * i, arc * (i + 1));
        ctx.fill();

        ctx.save();
        ctx.translate(center, center);
        ctx.rotate(arc * i + arc / 2);
        ctx.textAlign = "right";
        ctx.fillStyle = "#000";
        ctx.font = `${fontSize}px sans-serif`;
        ctx.fillText(options[i], textRadius, fontSize * 0.6);
        ctx.restore();
    }
}
