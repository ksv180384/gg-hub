<?php

return [
    'chunk_size' => max(1, (int) env('SERVER_MERGE_CHUNK_SIZE', 1000)),
];
