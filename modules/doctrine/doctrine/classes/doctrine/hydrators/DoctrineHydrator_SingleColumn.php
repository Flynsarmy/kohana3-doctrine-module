<?php
/**
 * Fetchs a flat array of values from the first column of the result set.
 */
class DoctrineHydrator_SingleColumn extends Doctrine_Hydrator_Abstract
{
    public function hydrateResultSet($stmt)
    {
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

Doctrine_Manager::getInstance()->registerHydrator('SingleColumn', 'DoctrineHydrator_SingleColumn');

/*
$setIds =
	Doctrine_Query::Create()
	->from('MediaSet mset')
	->select('mset.id')
	->where('mset.publishdate BETWEEN ? AND ?', array($nowTime->mysql(), $laterTime->mysql()))
	->orderBy('mset.publishdate asc')
	->limit($num_items)
	->execute(array(), 'SingleColumn');
*/