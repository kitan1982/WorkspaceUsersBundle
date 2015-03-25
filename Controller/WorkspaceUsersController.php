<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\WorkspaceUsersBundle\Controller;

use Claroline\CoreBundle\Entity\Role;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Form\RoleTranslationType;
use Claroline\CoreBundle\Manager\FacetManager;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Manager\RightsManager;
use Claroline\CoreBundle\Manager\RoleManager;
use Claroline\CoreBundle\Manager\UserManager;
use Claroline\CoreBundle\Manager\WorkspaceUserQueueManager;
use Claroline\WorkspaceUsersBundle\Form\WorkspaceRoleType;
use JMS\DiExtraBundle\Annotation as DI;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\SecurityContextInterface;

class WorkspaceUsersController extends Controller
{
    private $facetManager;
    private $formFactory;
    private $request;
    private $resourceManager;
    private $rightsManager;
    private $roleManager;
    private $securityContext;
    private $userManager;
    private $workspaceUserQueueManager;

    /**
     * @DI\InjectParams({
     *     "facetManager"              = @DI\Inject("claroline.manager.facet_manager"),
     *     "formFactory"               = @DI\Inject("form.factory"),
     *     "requestStack"              = @DI\Inject("request_stack"),
     *     "resourceManager"           = @DI\Inject("claroline.manager.resource_manager"),
     *     "rightsManager"             = @DI\Inject("claroline.manager.rights_manager"),
     *     "roleManager"               = @DI\Inject("claroline.manager.role_manager"),
     *     "securityContext"           = @DI\Inject("security.context"),
     *     "userManager"               = @DI\Inject("claroline.manager.user_manager"),
     *     "workspaceUserQueueManager" = @DI\Inject("claroline.manager.workspace_user_queue_manager")
     * })
     */
    public function __construct(
        FacetManager $facetManager,
        FormFactory $formFactory,
        RequestStack $requestStack,
        ResourceManager $resourceManager,
        RightsManager $rightsManager,
        RoleManager $roleManager,
        SecurityContextInterface $securityContext,
        UserManager $userManager,
        WorkspaceUserQueueManager $workspaceUserQueueManager
    )
    {
        $this->facetManager = $facetManager;
        $this->formFactory = $formFactory;
        $this->request = $requestStack->getCurrentRequest();
        $this->resourceManager = $resourceManager;
        $this->rightsManager = $rightsManager;
        $this->roleManager = $roleManager;
        $this->securityContext = $securityContext;
        $this->userManager = $userManager;
        $this->workspaceUserQueueManager = $workspaceUserQueueManager;
    }

    /**
     * @EXT\Route(
     *     "workspace/{workspace}/users/registered/page/{page}/max/{max}/ordered/by/{orderedBy}/order/{order}/search/{search}",
     *     name="claro_workspace_users_registered_user_list",
     *     defaults={"page"=1, "search"="", "max"=50, "orderedBy"="id", "order"="ASC"},
     *     options = {"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template()
     */
    public function workspaceUsersListAction(
        User $authenticatedUser,
        Workspace $workspace,
        $search = '',
        $page = 1,
        $max = 50,
        $orderedBy = 'id',
        $order = 'ASC'
    )
    {
        $this->checkWorkspaceUsersToolAccess($workspace);
        $canEdit = $this->hasWorkspaceUsersToolEditionAccess($workspace);
        $wsRoles = $this->roleManager->getRolesByWorkspace($workspace);
        $preferences = $this->facetManager->getVisiblePublicPreference();

        $pager = $search === '' ?
            $this->userManager->getByRolesIncludingGroups($wsRoles, $page, $max, $orderedBy, $order) :
            $this->userManager->getByRolesAndNameIncludingGroups($wsRoles, $search, $page, $max, $orderedBy, $order);

        return array(
            'workspace' => $workspace,
            'pager' => $pager,
            'search' => $search,
            'wsRoles' => $wsRoles,
            'max' => $max,
            'orderedBy' => $orderedBy,
            'order' => $order,
            'currentUser' => $authenticatedUser,
            'showMail' => $preferences['mail'],
            'canEdit' => $canEdit
        );
    }
    /**
     * @EXT\Route(
     *     "workspace/{workspace}/roles/list/ordered/by/{orderedBy}/order/{order}/search/{search}",
     *     name="claro_workspace_users_roles_list",
     *     defaults={"search"="", "orderedBy"="id", "order"="ASC"},
     *     options = {"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template()
     */
    public function workspaceRolesListAction(
        Workspace $workspace,
        $search = '',
        $orderedBy = 'id',
        $order = 'ASC'
    )
    {
        $this->checkWorkspaceUsersToolAccess($workspace);
        $this->checkWorkspaceUsersToolEditionAccess($workspace);
        $roles = $this->roleManager->getRolesByWorkspace(
            $workspace,
            $search,
            $orderedBy,
            $order
        );

        return array(
            'workspace' => $workspace,
            'roles' => $roles,
            'search' => $search,
            'orderedBy' => $orderedBy,
            'order' => $order
        );
    }

    /**
     * @EXT\Route(
     *     "workspace/{workspace}/pending/users/list",
     *     name="claro_workspace_users_pending_list"
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template()
     */
    public function workspaceUsersPendingListAction(Workspace $workspace)
    {
        $this->checkWorkspaceUsersToolAccess($workspace);
        $this->checkWorkspaceUsersToolEditionAccess($workspace);

        return array(
            'workspace' => $workspace,
            'pager' => $this->wksUqmanager->getAll($workspace)
        );
    }

    /**
     * @EXT\Route(
     *     "workspace/{workspace}/role/create/form",
     *     name="claro_workspace_users_role_create_form",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineWorkspaceUsersBundle:WorkspaceUsers:workspaceRoleCreateModalForm.html.twig")
     */
    public function workspaceRoleCreateFormAction(Workspace $workspace)
    {
        $this->checkWorkspaceUsersToolAccess($workspace);
        $this->checkWorkspaceUsersToolEditionAccess($workspace);
        $form = $this->formFactory->create(new WorkspaceRoleType());

        return array('workspace' => $workspace, 'form' => $form->createView());
    }

    /**
     * @EXT\Route(
     *     "workspace/{workspace}/role/create",
     *     name="claro_workspace_users_role_create",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineWorkspaceUsersBundle:WorkspaceUsers:workspaceRoleCreateModalForm.html.twig")
     */
    public function workspaceRoleCreateAction(Workspace $workspace, User $authenticatedUser)
    {
        $this->checkWorkspaceUsersToolAccess($workspace);
        $this->checkWorkspaceUsersToolEditionAccess($workspace);
        $role = new Role();
        $form = $this->formFactory->create(new WorkspaceRoleType(), $role);
        $form->handleRequest($this->request);

        if ($form->isValid()) {
            $translationKey = $role->getTranslationKey();
            $requireDir = $form->get('requireDir')->getData();
            $role = $this->roleManager->createWorkspaceRole(
                'ROLE_WS_' . strtoupper($translationKey) . '_' . $workspace->getGuid(),
                $translationKey,
                $workspace
            );

            //add the role to every resource of that workspace
            $nodes = $this->resourceManager->getByWorkspace($workspace);

            foreach ($nodes as $node) {
                $this->rightsManager->create(0, $role, $node, false, array());
            }

            if ($requireDir) {
                $resourceTypes = $this->resourceManager->getAllResourceTypes();
                $creations = array();

                foreach ($resourceTypes as $resourceType) {
                    $creations[] = array('name' => $resourceType->getName());
                }

                $this->resourceManager->create(
                    $this->resourceManager->createResource(
                        'Claroline\CoreBundle\Entity\Resource\Directory',
                        $translationKey
                    ),
                    $this->resourceManager->getResourceTypeByName('directory'),
                    $authenticatedUser,
                    $workspace,
                    $this->resourceManager->getWorkspaceRoot($workspace),
                    null,
                    array(
                        'ROLE_WS_' .  strtoupper($translationKey) => array(
                            'open' => true,
                            'edit' => true,
                            'copy' => true,
                            'delete' => true,
                            'export' => true,
                            'create' => $creations,
                            'role' => $role
                        ),
                        'ROLE_WS_MANAGER' => array(
                            'open' => true,
                            'edit' => true,
                            'copy' => true,
                            'delete' => true,
                            'export' => true,
                            'create' => $creations,
                            'role' => $this->roleManager->getManagerRole($workspace)
                        )
                    )
                );
            }

            return new JsonResponse(
                array('id' => $role->getId(), 'name' => $role->getTranslationKey()),
                200
            );
        } else {

            return array('form' => $form->createView(), 'workspace' => $workspace);
        }
    }

    /**
     * @EXT\Route(
     *     "workspace/{workspace}/role/{role}/edit/form",
     *     name="claro_workspace_users_role_edit_form",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineWorkspaceUsersBundle:WorkspaceUsers:workspaceRoleEditModalForm.html.twig")
     */
    public function workspaceRoleEditFormAction(Role $role, Workspace $workspace)
    {
        $this->checkWorkspaceUsersToolAccess($workspace);
        $this->checkWorkspaceUsersToolEditionAccess($workspace);
        $form = $this->formFactory->create(
            new RoleTranslationType($workspace->getGuid()),
            $role
        );

        return array(
            'role' => $role,
            'workspace' => $workspace,
            'form' => $form->createView()
        );
    }

    /**
     * @EXT\Route(
     *     "workspace/{workspace}/role/{role}/edit",
     *     name="claro_workspace_users_role_edit",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineWorkspaceUsersBundle:WorkspaceUsers:workspaceRoleEditModalForm.html.twig")
     */
    public function workspaceRoleEditAction(Role $role, Workspace $workspace)
    {
        $this->checkWorkspaceUsersToolAccess($workspace);
        $this->checkWorkspaceUsersToolEditionAccess($workspace);
        $form = $this->formFactory->create(
            new RoleTranslationType($workspace->getGuid()),
            $role
        );
        $form->handleRequest($this->request);

        if ($form->isValid()) {
            $this->roleManager->edit($role);

            return new JsonResponse(
                array('id' => $role->getId(), 'name' => $role->getTranslationKey()),
                200
            );
        } else {

            return array(
                'role' => $role,
                'workspace' => $workspace,
                'form' => $form->createView()
            );
        }
    }

    private function checkWorkspaceUsersToolAccess(Workspace $workspace)
    {
        if (!$this->securityContext->isGranted('claroline_workspace_users_tool', $workspace)) {

            throw new AccessDeniedException();
        }
    }

    private function checkWorkspaceUsersToolEditionAccess(Workspace $workspace)
    {
        if (!$this->securityContext->isGranted('claroline_workspace_users_tool', 'edit', $workspace)) {

            throw new AccessDeniedException();
        }
    }

    private function hasWorkspaceUsersToolEditionAccess(Workspace $workspace)
    {
        return $this->securityContext->isGranted(
            'claroline_workspace_users_tool',
            'edit',
            $workspace
        );
    }
}
