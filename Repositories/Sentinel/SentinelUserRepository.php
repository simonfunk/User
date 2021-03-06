<?php namespace Modules\User\Repositories\Sentinel;

use Cartalyst\Sentinel\Laravel\Facades\Activation;
use Cartalyst\Sentinel\Laravel\Facades\Sentinel;
use Illuminate\Support\Facades\Hash;
use Modules\User\Events\UserWasUpdated;
use Modules\User\Exceptions\UserNotFoundException;
use Modules\User\Repositories\UserRepository;

class SentinelUserRepository implements UserRepository
{
    /**
     * @var \Modules\User\Entities\Sentinel\User
     */
    protected $user;
    /**
     * @var \Cartalyst\Sentinel\Roles\EloquentRole
     */
    protected $role;

    public function __construct()
    {
        $this->user = Sentinel::getUserRepository()->createModel();
        $this->role = Sentinel::getRoleRepository()->createModel();
    }

    /**
     * Returns all the users
     * @return object
     */
    public function all()
    {
        return $this->user->all();
    }

    /**
     * Create a user resource
     * @param $data
     * @return mixed
     */
    public function create(array $data)
    {
        return $this->user->create((array) $data);
    }

    /**
     * Create a user and assign roles to it
     * @param  array $data
     * @param  array $roles
     * @param bool $activated
     */
    public function createWithRoles($data, $roles, $activated = false)
    {
        $this->hashPassword($data);
        $user = $this->create((array) $data);

        if (!empty($roles)) {
            $user->roles()->attach($roles);
        }

        if ($activated) {
            $activation = Activation::create($user);
            Activation::complete($user, $activation->code);
        }
    }

    /**
     * Find a user by its ID
     * @param $id
     * @return mixed
     */
    public function find($id)
    {
        return $this->user->find($id);
    }

    /**
     * Update a user
     * @param $user
     * @param $data
     * @return mixed
     */
    public function update($user, $data)
    {
        $user = $user->update($data);

        event(new UserWasUpdated($user));

        return $user;
    }

    /**
     * @param $userId
     * @param $data
     * @param $roles
     * @internal param $user
     * @return mixed
     */
    public function updateAndSyncRoles($userId, $data, $roles)
    {
        $user = $this->user->find($userId);

        $this->checkForNewPassword($data);

        $user = $user->fill($data);
        $user->save();

        event(new UserWasUpdated($user));

        if (!empty($roles)) {
            $user->roles()->sync($roles);
        }
    }

    /**
     * Deletes a user
     * @param $id
     * @throws UserNotFoundException
     * @return mixed
     */
    public function delete($id)
    {
        if ($user = $this->user->find($id)) {
            return $user->delete();
        };

        throw new UserNotFoundException();
    }

    /**
     * Find a user by its credentials
     * @param  array $credentials
     * @return mixed
     */
    public function findByCredentials(array $credentials)
    {
        return Sentinel::findByCredentials($credentials);
    }

    /**
     * Hash the password key
     * @param array $data
     */
    private function hashPassword(array &$data)
    {
        $data['password'] = Hash::make($data['password']);
    }

    /**
     * Check if there is a new password given
     * If not, unset the password field
     * @param array $data
     */
    private function checkForNewPassword(array &$data)
    {
        if (! $data['password']) {
            unset($data['password']);

            return;
        }

        $data['password'] = Hash::make($data['password']);
    }
}
