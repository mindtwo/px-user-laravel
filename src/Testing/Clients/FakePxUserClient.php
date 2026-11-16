<?php

namespace mindtwo\PxUserLaravel\Testing\Clients;

use mindtwo\PxUserLaravel\Contracts\PxUser as ContractsPxUser;
use mindtwo\PxUserLaravel\DataTransfer\PxUserData;
use mindtwo\PxUserLaravel\DataTransfer\PxUserDataWithPermissions;
use mindtwo\PxUserLaravel\Helper\Utils;
use mindtwo\PxUserLaravel\Http\Client\PxUserClient;
use mindtwo\PxUserLaravel\Testing\FakePxUserState;
use mindtwo\PxUserLaravel\Testing\PxUserEndpoint;
use RuntimeException;
use Throwable;

/**
 * Answers PX User API calls from FakePxUser's users and tokens, without HTTP requests.
 *
 * @internal Bound in place of PxUserClient by FakePxUser::fake().
 */
class FakePxUserClient extends PxUserClient
{
    public function __construct(private readonly FakePxUserState $state) {}

    public function getUser(): PxUserData
    {
        $this->state->call(PxUserEndpoint::User, $this->context());

        return PxUserData::from($this->subject());
    }

    /**
     * {@inheritDoc}
     */
    public function getUsersDetails(string|array $userIds, ?string $productCode = null): PxUserData|array
    {
        $ids = (array) $userIds;

        $this->state->call(PxUserEndpoint::UsersDetails, [
            ...$this->context(),
            'user_ids' => $ids,
            'product_code' => $productCode ?? Utils::productCode(),
        ]);

        $users = collect($ids)
            ->map(fn (string $id) => $this->state->find($id))
            ->filter()
            ->map(fn (array $user) => PxUserData::from($user))
            ->values()
            ->all();

        if (is_string($userIds)) {
            return $users[0] ?? throw FakePxUserState::requestException(404, 'User not found');
        }

        return $users;
    }

    public function getUserWithPermissions(bool $withExtendedProducts = false): PxUserDataWithPermissions
    {
        $this->state->call(PxUserEndpoint::UserWithPermissions, [
            ...$this->context(),
            'with_extended_products' => $withExtendedProducts,
        ]);

        return PxUserDataWithPermissions::from($this->subject());
    }

    public function getUsers(string $names, ?string $productContext = null): array
    {
        $this->state->call(PxUserEndpoint::Users, [
            ...$this->context(),
            'names' => $names,
            'product_code' => $productContext ?? Utils::productCode(),
        ]);

        $users = $this->state->search($names);

        // PX answers a search without matches with 404
        throw_if($users === [], FakePxUserState::requestException(404, 'No users found'));

        return $users;
    }

    public function refreshTokens(string $refreshToken): array
    {
        $this->setAccessToken($refreshToken);

        $this->state->call(PxUserEndpoint::RefreshTokens, ['refresh_token' => $refreshToken]);

        $pxUserId = $this->state->ownerOfRefreshToken($refreshToken)
            ?? throw FakePxUserState::requestException(401, 'Invalid refresh token');

        return $this->state->issueTokens($pxUserId);
    }

    public function logout(): array
    {
        $this->state->call(PxUserEndpoint::Logout, $this->context());

        return ['success' => true];
    }

    public function checkEipConnection(): array
    {
        $this->state->call(PxUserEndpoint::EipCheck, $this->context());

        return ['connected' => true];
    }

    /**
     * Get the request context the real client sends, aborting with 401 like it for a call without a user.
     *
     * @return array<string, string>
     */
    private function context(): array
    {
        $user = null;

        if ($this->accessTokenOverride === null) {
            $user = auth()->user();
            abort_if(! $user instanceof ContractsPxUser, 401, 'Unauthenticated.');
        }

        return array_filter([
            'access_token' => $this->accessTokenOverride ?? $this->storedAccessToken($user),
            'tenant_code' => $this->tenantCodeOverride ?? $user?->getPxUserTenantCode(),
            'domain_code' => $this->domainCodeOverride ?? $user?->getPxUserDomainCode(),
        ], fn (?string $value) => $value !== null);
    }

    /**
     * Get the user the access token or the authenticated user stands for.
     *
     * @return array<string, mixed>
     */
    private function subject(): array
    {
        $user = $this->accessTokenOverride !== null
            ? $this->state->userForAccessToken($this->accessTokenOverride)
            : $this->state->find($this->authenticatedPxUser()->getPxUserId());

        return $user ?? throw new RuntimeException('Empty user returned');
    }

    private function authenticatedPxUser(): ContractsPxUser
    {
        $user = auth()->user();
        abort_if(! $user instanceof ContractsPxUser, 401, 'Unauthenticated.');

        return $user;
    }

    /**
     * Get the user's stored access token; the fake answers users without one too.
     */
    private function storedAccessToken(?ContractsPxUser $user): ?string
    {
        try {
            return $user?->getPxUserAccessToken();
        } catch (Throwable) {
            return null;
        }
    }
}
