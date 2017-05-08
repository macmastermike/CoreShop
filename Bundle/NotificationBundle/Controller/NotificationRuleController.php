<?php

namespace CoreShop\Bundle\NotificationBundle\Controller;

use CoreShop\Bundle\ResourceBundle\Controller\ResourceController;
use CoreShop\Component\Notification\Model\NotificationRuleInterface;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;

class NotificationRuleController extends ResourceController
{
    public function getConfigAction(Request $request)
    {
        $conditions = [];
        $actions = [];
        $types = [];

        $actionTypes = $this->getParameter('coreshop.notification_rule.actions.types');
        $conditionTypes = $this->getParameter('coreshop.notification_rule.conditions.types');

        foreach ($actionTypes as $type)
        {
            if (!in_array($type, $types)) {
                $types[] = $type;
            }
        }

        foreach ($conditionTypes as $type)
        {
            if (!in_array($type, $types)) {
                $types[] = $type;
            }
        }

        foreach ($types as $type) {
            $actionParameter = 'coreshop.notification_rule.actions.' . $type;
            $conditionParameter = 'coreshop.notification_rule.conditions.' . $type;

            if ($this->container->hasParameter($actionParameter)) {
                if (!array_key_exists($type, $actions)) {
                    $actions[$type] = [];
                }

                $actions[$type] = array_merge($actions[$type], array_keys($this->getParameter($actionParameter)));
            }

            if ($this->container->hasParameter($conditionParameter)) {
                if (!array_key_exists($type, $conditions)) {
                    $conditions[$type] = [];
                }

                $conditions[$type] = array_merge($conditions[$type], array_keys($this->getParameter($conditionParameter)));
            }
        }

        return $this->viewHandler->handle([
            'success' => true,
            'types' => $types,
            'actions' => $actions,
            'conditions' => $conditions
        ]);
    }

    public function sortAction(Request $request)
    {
        $rule = $request->get("rule");
        $toRule = $request->get("toRule");
        $position = $request->get("position");

        $rule = $this->repository->find($rule);
        $toRule = $this->repository->find($toRule);

        $direction = $rule->getSort() < $toRule->getSort() ? 'down' : 'up';

        if ($direction === 'down') {
            //Update all records in between and move one direction up.

            $fromSort = $rule->getSort()+1;
            $toSort = $toRule->getSort();

            if ($position === 'before') {
                $toSort -= 1;
            }

            $criteria = new Criteria();
            $criteria->where($criteria->expr()->gte('sort', $fromSort));
            $criteria->where($criteria->expr()->lte('sort', $toSort));

            $result = $this->repository->matching($criteria);

            foreach ($result as $newRule) {
                if($newRule instanceof NotificationRuleInterface) {
                    $newRule->setSort($newRule->getSort() - 1);

                    $this->entityManager->persist($newRule);
                }
            }

            $rule->setSort($toSort);

            $this->entityManager->persist($rule);
        } else {
            //Update all records in between and move one direction down.

            $fromSort = $toRule->getSort();
            $toSort = $rule->getSort();

            $criteria = new Criteria();
            $criteria->where($criteria->expr()->gte('sort', $fromSort));
            $criteria->where($criteria->expr()->lte('sort', $toSort));

            $result = $this->repository->matching($criteria);

            foreach ($result as $newRule) {
                if($newRule instanceof NotificationRuleInterface) {
                    $newRule->setSort($newRule->getSort() + 1);

                    $this->entityManager->persist($newRule);
                }
            }

            $rule->setSort($fromSort);

            $this->entityManager->persist($rule);
        }

        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }
}