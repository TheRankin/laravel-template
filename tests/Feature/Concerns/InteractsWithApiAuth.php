<?php

namespace Tests\Feature\Concerns;

use App\Models\User;

trait InteractsWithApiAuth
{
    /**
     * Authenticate subsequent JSON requests as the given user using a
     * bearer token written directly to the users.api_token column.
     */
    protected function actingAsToken(User $user): static
    {
        $user->api_token = 'test-token-' . $user->id;
        $user->save();

        $this->withHeader('Authorization', 'Bearer ' . $user->api_token);
        $this->withHeader('Accept', 'application/json');

        return $this;
    }
}
