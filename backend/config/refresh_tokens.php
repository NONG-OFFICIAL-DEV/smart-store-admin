<?php

return [
    // Minutes. Own env var deliberately, rather than reusing JWT_REFRESH_TTL —
    // that key becomes vestigial for tymon's own purposes once refresh() stops
    // calling ->refresh(), and reusing it would tie two independent concerns
    // to one name. Defaults to the same figure the app already used (14 days).
    'ttl' => (int) env('REFRESH_TOKEN_TTL', 20160),
];
