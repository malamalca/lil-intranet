<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;

/**
 * Users Controller
 *
 * @property \App\Model\Table\UsersTable $Users
 *
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class UsersController extends AppController
{
    /**
     * Initialize function
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * BeforeFilter method.
     *
     * @param \Cake\Event\Event $event Cake Event object.
     * @return \Cake\Http\Response|void|null
     */
    public function beforeFilter($event)
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated(['login', 'reset', 'changePassword']);

        if ($this->getRequest()->getParam('action') == 'login') {
            $this->Security->setConfig('validatePost', false);
        }

        return null;
    }

    /**
     * This method will display login form
     *
     * @return \Cake\Http\Response|void|null
     */
    public function login()
    {
        $this->Authorization->skipAuthorization();

        $result = $this->Authentication->getResult();

        // regardless of POST or GET, redirect if user is logged in
        if ($result->isValid()) {
            $user = $this->Authentication->getIdentity();

            /*$this->loadModel('AuditLogins');
            $auditLogin = $this->AuditLogins->newEntity([]);
            $auditLogin->user_id = $user->id;
            $auditLogin->date = new FrozenTime();
            $auditLogin->ip = $this->getRequest()->clientIp();
            $this->AuditLogins->save($auditLogin);*/

            $redirect = $this->getRequest()->getQuery('redirect', ['controller' => 'Pages', 'action' => 'index']);

            return $this->redirect($redirect);
        }

        // display error if user submitted and authentication failed
        if ($this->getRequest()->is(['post']) && !$result->isValid()) {
            $this->Flash->error('Invalid username or password');
        }

        return null;
    }

    /**
     * Logout method
     *
     * @return \Cake\Http\Response|void|null
     */
    public function logout()
    {
        $this->Authorization->skipAuthorization();

        $this->Authentication->logout();

        return $this->redirect('/');
    }

    /**
     * Reset method
     *
     * @return \Cake\Http\Response|void|null
     */
    public function reset()
    {
        $this->Authorization->skipAuthorization();

        if ($this->getRequest()->is('post')) {
            /** @var \App\Model\Entity\User $user */
            $user = $this->Users->find()
                ->select()
                ->where(['email' => $this->getRequest()->getData('email')])
                ->first();

            if ($user != false) {
                $this->Users->sendResetEmail($user);
                $this->Flash->success(__('An email with password reset instructions has been sent.'));
            } else {
                $this->Flash->error(__('No user with specified email has been found.'));
            }
        }

        return null;
    }

    /**
     * Change users password
     *
     * @param string $resetKey Auto generated reset key.
     * @return \Cake\Http\Response|void|null
     */
    public function changePassword($resetKey = null)
    {
        $this->Authorization->skipAuthorization();

        if (!$resetKey) {
            throw new NotFoundException(__('Reset key does not exist.'));
        }

        /** @var \App\Model\Entity\User $user */
        $user = $this->Users->find()
            ->select()
            ->where(['reset_key' => $resetKey])
            ->first();

        if (empty($user)) {
            throw new NotFoundException(__('User does not exist.'));
        }

        if ($this->getRequest()->is(['patch', 'post', 'put'])) {
            $this->Users->patchEntity($user, $this->getRequest()->getData(), ['validate' => 'resetPassword']);

            if (!$user->getErrors() && $this->Users->save($user)) {
                $this->Flash->success(__('Password has been changed.'));
                $this->redirect('/');
            } else {
                $this->Flash->error(__('Please verify that the information is correct.'));
            }
        }

        $this->set(compact('user'));

        return null;
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|void|null
     */
    public function index()
    {
        $q = $this->Users->find()
            ->where(['company_id' => $this->getCurrentUser()->get('company_id')])
            ->order('name');

        $filter = $this->Users->filter($q, $this->getRequest());
        $users = $q->all();

        $this->set(compact('users'));

        return null;
    }

    /**
     * View method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|void|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $user = $this->Users->get($id);

        $this->set('user', $user);

        return null;
    }

    /**
     * Immediatelly login as specified user
     *
     * @param string $id User id.
     * @return \Cake\Http\Response
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function loginAs($id)
    {
        $user = $this->Users->get($id);
        $ret = $this->Authentication->setIdentity($user);

        return $this->redirect('/');
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|void|null
     */
    public function add()
    {
        $this->setAction('edit');

        return null;
    }

    /**
     * Edit method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|void|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        if ($id) {
            $user = $this->Users->get($id);
        } else {
            /** @var \App\Model\Entity\User $user  */
            $user = $this->Users->newEmptyEntity();
            $user->company_id = $this->getCurrentUser()->get('company_id');
        }
        if ($this->getRequest()->is(['patch', 'post', 'put'])) {
            $user = $this->Users->patchEntity($user, $this->getRequest()->getData());
            if ($this->Users->save($user)) {
                $this->Flash->success(__('The user has been saved.'));

                return $this->redirect(['action' => 'view', $user->id]);
            }
            $this->Flash->error(__('The user could not be saved. Please, try again.'));
        }

        $this->set(compact('user'));

        return null;
    }

    /**
     * Properties method is for users editing their own data.
     *
     * @return \Cake\Http\Response|void|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function properties()
    {
        $user = $this->Users->get($this->getCurrentUser()->get('id'));

        if ($this->getRequest()->is(['patch', 'post', 'put'])) {
            $user = $this->Users->patchEntity($user, $this->getRequest()->getData(), ['validate' => 'properties']);
            if ($this->Users->save($user)) {
                $this->Flash->success(__('Properties have been saved.'));

                return $this->redirect(['action' => 'view', $user->id]);
            }
            $this->Flash->error(__('Properties could not be saved. Please, try again.'));
        }

        $this->set(compact('user'));

        return null;
    }

    /**
     * Delete method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->getRequest()->allowMethod(['get']);
        $user = $this->Users->get($id);
        if ($this->Users->delete($user)) {
            $this->Flash->success(__('The user has been deleted.'));
        } else {
            $this->Flash->error(__('The user could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}