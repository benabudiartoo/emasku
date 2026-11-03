<?php

return [
    // Downloads per owner per minute. Building a file costs some memory and time, so this guards the endpoint.
    'throttle_per_minute' => 20,
];
