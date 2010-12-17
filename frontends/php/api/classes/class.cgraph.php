<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
/**
 * File containing graph class for API.
 * @package API
 */
/**
 * Class containing methods for operations with graphs
 */
class CGraph extends CZBXAPI{
/**
* Get graph data
*
* @param array $options
* @return array
*/
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];
		$result = array();

		$sort_columns = array('graphid','name'); // allowed columns for sorting
		$subselects_allowed_outputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND, API_OUTPUT_CUSTOM); // allowed output options for [ select_* ] params

		$sql_parts = array(
			'select' => array('graphs' => 'g.graphid'),
			'from' => array('graphs' => 'graphs g'),
			'where' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null,
		);

		$def_options = array(
			'nodeids' 				=> null,
			'groupids' 				=> null,
			'templateids'			=> null,
			'hostids' 				=> null,
			'graphids' 				=> null,
			'itemids' 				=> null,
			'discoveryids' 			=> null,
			'type' 					=> null,
			'templated'				=> null,
			'inherited'				=> null,
			'editable'				=> null,
			'nopermissions'			=> null,

// filter
			'filter'					=> null,
			'search'					=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,

// output
			'output'				=> API_OUTPUT_REFER,
			'selectGroups'			=> null,
			'select_templates'		=> null,
			'selectHosts'			=> null,
			'selectItems'			=> null,
			'select_graph_items'	=> null,
			'selectDiscoveryRule'	=> null,
			'countOutput'			=> null,
			'groupCount'			=> null,
			'preservekeys'			=> null,

			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		);

		$options = zbx_array_merge($def_options, $options);


		if(is_array($options['output'])){
			unset($sql_parts['select']['graphs']);
			foreach($options['output'] as $key => $field){
				$sql_parts['select'][$field] = ' g.'.$field;
			}

			$options['output'] = API_OUTPUT_CUSTOM;
		}

// editable + PERMISSION CHECK

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sql_parts['from']['graphs_items'] = 'graphs_items gi';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';
			$sql_parts['from']['rights'] = 'rights r';
			$sql_parts['from']['users_groups'] = 'users_groups ug';
			$sql_parts['where']['gig'] = 'gi.graphid=g.graphid';
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';
			$sql_parts['where'][] = 'r.id=hg.groupid ';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = 'NOT EXISTS( '.
											' SELECT gii.graphid '.
											' FROM graphs_items gii, items ii '.
											' WHERE gii.graphid=g.graphid '.
												' AND gii.itemid=ii.itemid '.
												' AND EXISTS( '.
													' SELECT hgg.groupid '.
													' FROM hosts_groups hgg, rights rr, users_groups ugg '.
													' WHERE ii.hostid=hgg.hostid '.
														' AND rr.id=hgg.groupid '.
														' AND rr.groupid=ugg.usrgrpid '.
														' AND ugg.userid='.$userid.
														' AND rr.permission<'.$permission.'))';
		}


// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);

			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}

			$sql_parts['from']['graphs_items'] = 'graphs_items gi';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts_groups'] = 'hosts_groups hg';

			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where'][] = 'hg.hostid=i.hostid';
			$sql_parts['where']['gig'] = 'gi.graphid=g.graphid';
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
			$sql_parts['where']['hgi'] = 'hg.hostid=i.hostid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['hg'] = 'hg.groupid';
			}
		}

// templateids
		if(!is_null($options['templateids'])){
			zbx_value2array($options['templateids']);

			if(!is_null($options['hostids'])){
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else{
				$options['hostids'] = $options['templateids'];
			}
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['hostid'] = 'i.hostid';
			}

			$sql_parts['from']['graphs_items'] = 'graphs_items gi';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['where'][] = DBcondition('i.hostid', $options['hostids']);
			$sql_parts['where']['gig'] = 'gi.graphid=g.graphid';
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['i'] = 'i.hostid';
			}
		}

// graphids
		if(!is_null($options['graphids'])){
			zbx_value2array($options['graphids']);

			$sql_parts['where'][] = DBcondition('g.graphid', $options['graphids']);
		}

// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['itemid'] = 'gi.itemid';
			}
			$sql_parts['from']['graphs_items'] = 'graphs_items gi';
			$sql_parts['where']['gig'] = 'gi.graphid=g.graphid';
			$sql_parts['where'][] = DBcondition('gi.itemid', $options['itemids']);

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['gi'] = 'gi.itemid';
			}
		}

// discoveryids
		if(!is_null($options['discoveryids'])){
			zbx_value2array($options['discoveryids']);
			if($options['output'] != API_OUTPUT_SHORTEN){
				$sql_parts['select']['itemid'] = 'id.parent_itemid';
			}
			$sql_parts['from']['graphs_items'] = 'graphs_items gi';
			$sql_parts['from']['item_discovery'] = 'item_discovery id';
			$sql_parts['where']['gig'] = 'gi.graphid=g.graphid';
			$sql_parts['where']['giid'] = 'gi.itemid=id.itemid';
			$sql_parts['where'][] = DBcondition('id.parent_itemid', $options['discoveryids']);

			if(!is_null($options['groupCount'])){
				$sql_parts['group']['id'] = 'id.parent_itemid';
			}
		}

// type
		if(!is_null($options['type'] )){
			$sql_parts['where'][] = 'g.type='.$options['type'];
		}

// templated
		if(!is_null($options['templated'])){
			$sql_parts['from']['graphs_items'] = 'graphs_items gi';
			$sql_parts['from']['items'] = 'items i';
			$sql_parts['from']['hosts'] = 'hosts h';
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
			$sql_parts['where']['ggi'] = 'g.graphid=gi.graphid';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';

			if($options['templated']){
				$sql_parts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else{
				$sql_parts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

// inherited
		if(!is_null($options['inherited'])){
			if($options['inherited']){
				$sql_parts['where'][] = 'g.templateid IS NOT NULL';
			}
			else{
				$sql_parts['where'][] = 'g.templateid IS NULL';
			}
		}

// extendoutput
		if($options['output'] == API_OUTPUT_EXTEND){
			$sql_parts['select']['graphs'] = 'g.*';
		}

// countOutput
		if(!is_null($options['countOutput'])){
			$options['sortfield'] = '';
			$sql_parts['select'] = array('count(DISTINCT g.graphid) as rowscount');

//groupCount
			if(!is_null($options['groupCount'])){
				foreach($sql_parts['group'] as $key => $fields){
					$sql_parts['select'][$key] = $fields;
				}
			}
		}

// search
		if(is_array($options['search'])){
			zbx_db_search('graphs g', $options, $sql_parts);
		}

// filter
		if(is_null($options['filter']))
			$options['filter'] = array();

		if(is_array($options['filter'])){
			if(!array_key_exists('flags', $options['filter']))
				$options['filter']['flags'] = array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED);

			zbx_db_filter('graphs g', $options, $sql_parts);

			if(isset($options['filter']['host'])){
				zbx_value2array($options['filter']['host']);

				$sql_parts['from']['graphs_items'] = 'graphs_items gi';
				$sql_parts['from']['items'] = 'items i';
				$sql_parts['from']['hosts'] = 'hosts h';
				$sql_parts['where']['gig'] = 'gi.graphid=g.graphid';
				$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';

				$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
				$sql_parts['where']['host'] = DBcondition('h.host', $options['filter']['host'], false, true);
			}

			if(isset($options['filter']['hostid'])){
				zbx_value2array($options['filter']['hostid']);

				$sql_parts['from']['graphs_items'] = 'graphs_items gi';
				$sql_parts['from']['items'] = 'items i';
				$sql_parts['where']['gig'] = 'gi.graphid=g.graphid';
				$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';

				$sql_parts['where']['hostid'] = DBcondition('i.hostid', $options['filter']['hostid']);
			}
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'g.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('g.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('g.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'g.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//------------

		$graphids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['group'] = array_unique($sql_parts['group']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_group = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['group']))		$sql_where.= ' GROUP BY '.implode(',',$sql_parts['group']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sql_parts).' '.$sql_select.
				' FROM '.$sql_from.
				' WHERE '.DBin_node('g.graphid', $nodeids).
					$sql_where.
				$sql_group.
				$sql_order;
//SDI($sql);
		$db_res = DBselect($sql, $sql_limit);
		while($graph = DBfetch($db_res)){
			if(!is_null($options['countOutput'])){
				if(!is_null($options['groupCount']))
					$result[] = $graph;
				else
					$result = $graph['rowscount'];
			}
			else{
				$graphids[$graph['graphid']] = $graph['graphid'];

				if($options['output'] == API_OUTPUT_SHORTEN){
					$result[$graph['graphid']] = array('graphid' => $graph['graphid']);
				}
				else{
					if(!isset($result[$graph['graphid']]))
						$result[$graph['graphid']]= array();

					if(!is_null($options['selectHosts']) && !isset($result[$graph['graphid']]['hosts'])){
						$result[$graph['graphid']]['hosts'] = array();
					}
					if(!is_null($options['select_graph_items']) && !isset($result[$graph['graphid']]['gitems'])){
						$result[$graph['graphid']]['gitems'] = array();
					}
					if(!is_null($options['select_templates']) && !isset($result[$graph['graphid']]['templates'])){
						$result[$graph['graphid']]['templates'] = array();
					}
					if(!is_null($options['selectItems']) && !isset($result[$graph['graphid']]['items'])){
						$result[$graph['graphid']]['items'] = array();
					}
					if(!is_null($options['selectDiscoveryRule']) && !isset($result[$graph['graphid']]['discoveryRule'])){
						$result[$graph['graphid']]['discoveryRule'] = array();
					}

// hostids
					if(isset($graph['hostid']) && is_null($options['selectHosts'])){
						if(!isset($result[$graph['graphid']]['hosts']))
							$result[$graph['graphid']]['hosts'] = array();

						$result[$graph['graphid']]['hosts'][] = array('hostid' => $graph['hostid']);
						unset($graph['hostid']);
					}
// itemids
					if(isset($graph['itemid']) && is_null($options['selectItems'])){
						if(!isset($result[$graph['graphid']]['items']))
							$result[$graph['graphid']]['items'] = array();

						$result[$graph['graphid']]['items'][] = array('itemid' => $graph['itemid']);
						unset($graph['itemid']);
					}

					$result[$graph['graphid']] += $graph;
				}
			}
		}

COpt::memoryPick();
		if(!is_null($options['countOutput'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding GraphItems
		if(!is_null($options['select_graph_items']) && str_in_array($options['select_graph_items'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['select_graph_items'],
				'graphids' => $graphids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$gitems = CGraphItem::get($obj_params);
//SDI($gitems);
			foreach($gitems as $gitemid => $gitem){
				$ggraphs = $gitem['graphs'];
				unset($gitem['graphs']);
				foreach($ggraphs as $num => $graph){
					$result[$graph['graphid']]['gitems'][] = $gitem;
				}
			}
		}

// Adding Hostgroups
		if(!is_null($options['selectGroups'])){
			if(is_array($options['selectGroups']) || str_in_array($options['selectGroups'], $subselects_allowed_outputs)){
				$obj_params = array(
					'nodeids' => $nodeids,
					'output' => $options['selectGroups'],
					'graphids' => $graphids,
					'nopermissions' => 1,
					'preservekeys' => 1
				);
				$groups = CHostGroup::get($obj_params);

				foreach($groups as $groupis => $group){
					$ggraphs = $group['graphs'];
					unset($group['graphs']);
					foreach($ggraphs as $num => $graph){
						$result[$graph['graphid']]['groups'][] = $group;
					}
				}
			}
		}

// Adding Hosts
		if(!is_null($options['selectHosts'])){
			if(is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselects_allowed_outputs)){
				$obj_params = array(
					'nodeids' => $nodeids,
					'output' => $options['selectHosts'],
					'graphids' => $graphids,
					'nopermissions' => 1,
					'preservekeys' => 1
				);
				$hosts = CHost::get($obj_params);
				foreach($hosts as $hostid => $host){
					$hgraphs = $host['graphs'];
					unset($host['graphs']);
					foreach($hgraphs as $num => $graph){
						$result[$graph['graphid']]['hosts'][] = $host;
					}
				}
			}
		}

// Adding Templates
		if(!is_null($options['select_templates']) && str_in_array($options['select_templates'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['select_templates'],
				'graphids' => $graphids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$templates = CTemplate::get($obj_params);
			foreach($templates as $templateid => $template){
				$tgraphs = $template['graphs'];
				unset($template['graphs']);
				foreach($tgraphs as $num => $graph){
					$result[$graph['graphid']]['templates'][] = $template;
				}
			}
		}

// Adding Items
		if(!is_null($options['selectItems']) && str_in_array($options['selectItems'], $subselects_allowed_outputs)){
			$obj_params = array(
				'nodeids' => $nodeids,
				'output' => $options['selectItems'],
				'graphids' => $graphids,
				'nopermissions' => 1,
				'preservekeys' => 1
			);
			$items = CItem::get($obj_params);
			foreach($items as $itemid => $item){
				$igraphs = $item['graphs'];
				unset($item['graphs']);
				foreach($igraphs as $num => $graph){
					$result[$graph['graphid']]['items'][] = $item;
				}
			}
		}

// Adding discoveryRule
		if(!is_null($options['selectDiscoveryRule'])){
			$ruleids = $rule_map = array();

			$sql = 'SELECT id.parent_itemid, gd.graphid'.
					' FROM graph_discovery gd, item_discovery id,  graphs_items gi'.
					' WHERE '.DBcondition('gd.graphid', $graphids).
						' AND gd.parent_graphid=gi.graphid'.
						' AND gi.itemid=id.itemid';
			$db_rules = DBselect($sql);
			while($rule = DBfetch($db_rules)){
				$ruleids[$rule['parent_itemid']] = $rule['parent_itemid'];
				$rule_map[$rule['graphid']] = $rule['parent_itemid'];
			}

			$obj_params = array(
				'nodeids' => $nodeids,
				'itemids' => $ruleids,
				'nopermissions' => 1,
				'preservekeys' => 1,
			);

			if(is_array($options['selectDiscoveryRule']) || str_in_array($options['selectDiscoveryRule'], $subselects_allowed_outputs)){
				$obj_params['output'] = $options['selectDiscoveryRule'];
				$discoveryRules = CItem::get($obj_params);

				foreach($result as $graphid => $graph){
					if(isset($rule_map[$graphid]) && isset($discoveryRules[$rule_map[$graphid]])){
						$result[$graphid]['discoveryRule'] = $discoveryRules[$rule_map[$graphid]];
					}
				}
			}
		}

COpt::memoryPick();
// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}


/**
 * Get graphid by graph name
 *
 * params: hostids, name
 *
 * @param array $graphData
 * @return string|boolean
 */
	public static function getObjects($graphData){
		$options = array(
			'filter' => $graphData,
			'output'=>API_OUTPUT_EXTEND
		);

		if(isset($graphData['node']))
			$options['nodeids'] = getNodeIdByNodeName($graphData['node']);
		else if(isset($graphData['nodeids']))
			$options['nodeids'] = $graphData['nodeids'];

		$result = self::get($options);

	return $result;
	}

	public static function exists($object){
		$options = array(
			'filter' => array('flags' => null),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
			'limit' => 1
		);
		if(isset($object['name'])) $options['filter']['name'] = $object['name'];
		if(isset($object['host'])) $options['filter']['host'] = $object['host'];
		if(isset($object['hostids'])) $options['hostids'] = zbx_toArray($object['hostids']);

		if(isset($object['node']))
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		else if(isset($object['nodeids']))
			$options['nodeids'] = $object['nodeids'];

		$objs = self::get($options);

	return !empty($objs);
	}

/**
 * Create new graphs
 *
 * @param array $graphs
 * @return boolean
 */
	public static function create($graphs){
		$graphs = zbx_toArray($graphs);
		$graphids = array();

		try{
			self::BeginTransaction(__METHOD__);

			self::checkInput($graphs, false);

			foreach($graphs as $gnum => $graph){

				$options = array(
					'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
					'output' => API_OUTPUT_EXTEND,
					'editable' => 1,
					'templated_hosts' => 1,
				);
				$graph_hosts = CHost::get($options);

// check - items from one template
				$templated_graph = false;
				foreach($graph_hosts as $host){
					if(HOST_STATUS_TEMPLATE == $host['status']){
						$templated_graph = $host['hostid'];
						break;
					}
				}
				if($templated_graph && (count($graph_hosts) > 1)){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_GRAPH.' [ '.$graph['name'].' ] '.S_GRAPH_TEMPLATE_HOST_CANNOT_OTHER_ITEMS_HOSTS_SMALL);
				}

// check ymin, ymax items
				self::checkAxisItems($graph, $templated_graph);

				$graphid = self::createReal($graph);

				if($templated_graph){
					$graph['graphid'] = $graphid;
					self::inherit($graph);
				}

				$graphids[] = $graphid;
			}

			self::EndTransaction(true, __METHOD__);
			return array('graphids' => $graphids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, $error);
			return false;
		}
	}

/**
 * Update existing graphs
 *
 * @param array $graphs
 * @return boolean
 */
	public static function update($graphs){
		$graphs = zbx_toArray($graphs);
		$graphids = zbx_objectValues($graphs, 'graphid');

		try{
			self::BeginTransaction(__METHOD__);

// GRAPHS PERMISSIONS {{{
			$options = array(
				'graphids' => $graphids,
				'editable' => 1,
				'preservekeys' => 1,
				'output' => API_OUTPUT_SHORTEN,
				'select_graph_items'=> API_OUTPUT_EXTEND
			);
			$upd_graphs = self::get($options);

			foreach($graphs as $gnum => $graph){
				if(!isset($upd_graphs[$graph['graphid']])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
				}

				if(!isset($graph['gitems']))
					$graphs[$gnum]['gitems'] = $upd_graphs[$graph['graphid']]['gitems'];
			}

// }}} GRAPHS PERMISSIONS

			self::checkInput($graphs, true);

			foreach($graphs as $gnum => $graph){

				unset($graph['templateid']);

				$options = array(
					'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
					'output' => API_OUTPUT_EXTEND,
					'editable' => 1,
					'templated_hosts' => 1,
				);
				$graph_hosts = CHost::get($options);

// EXCEPTION: MESS TEMPLATED ITEMS {{{
				$templated_graph = false;
				foreach($graph_hosts as $host){
					if(HOST_STATUS_TEMPLATE == $host['status']){
						$templated_graph = $host['hostid'];
						break;
					}
				}
				if($templated_graph && (count($graph_hosts) > 1)){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_GRAPH.' [ '.$graph['name'].' ] '.S_GRAPH_TEMPLATE_HOST_CANNOT_OTHER_ITEMS_HOSTS_SMALL);
				}
// }}} EXCEPTION: MESS TEMPLATED ITEMS

// check ymin, ymax items
				self::checkAxisItems($graph, $templated_graph);

				self::updateReal($graph);
// inheritance
				if($templated_graph) self::inherit($graph);
			}

			self::EndTransaction(true, __METHOD__);
			return array('graphids' => $graphids);
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, $error);
			return false;
		}
	}

	protected static function createReal($graph){
		$graphids = DB::insert('graphs', array($graph));
		$graphid = reset($graphids);

		foreach($graph['gitems'] as $gitem){
			$gitem['graphid'] = $graphid;

			DB::insert('graphs_items', array($gitem));
		}

		return $graphid;
	}

	protected static function updateReal($graph){
		$data = array(array('values' => $graph, 'where'=> array('graphid='.$graph['graphid'])));
		DB::update('graphs', $data);

		if(isset($graph['gitems'])){
			DB::delete('graphs_items', array('graphid'=>$graph['graphid']));

			foreach($graph['gitems'] as $inum => $gitem){
				$gitem['graphid'] = $graph['graphid'];

				DB::insert('graphs_items', array($gitem));
			}
		}

		return $graph['graphid'];
	}

	protected static function inherit($graph, $hostids=null){
		$options = array(
			'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => 1,
		);
		$graph_templates = CTemplate::get($options);

		if(empty($graph_templates)) return true;


		$graphTemplate = reset($graph_templates);
		$options = array(
			'templateids' => $graphTemplate['templateid'],
			'output' => array('hostid', 'host'),
			'preservekeys' => 1,
			'hostids' => $hostids,
			'nopermissions' => 1,
			'templated_hosts' => 1,
		);
		$chd_hosts = CHost::get($options);

		$options = array(
			'graphids' => $graph['graphid'],
			'nopermissions' => 1,
			'selectItems' => API_OUTPUT_EXTEND,
			'select_graph_items' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND
		);
		$graph = self::get($options);
		$graph = reset($graph);

		foreach($chd_hosts as $chd_host){
			$tmp_graph = $graph;
			$tmp_graph['templateid'] = $graph['graphid'];

			if(!$tmp_graph['gitems'] = get_same_graphitems_for_host($tmp_graph['gitems'], $chd_host['hostid']))
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$tmp_graph['name'].' ]: cannot inherit. No required items on [ '.$chd_host['host'].' ]');

			if($tmp_graph['ymax_itemid'] > 0){
				$ymax_itemid = get_same_graphitems_for_host(array(array('itemid' => $tmp_graph['ymax_itemid'])), $chd_host['hostid']);
				if(!$ymax_itemid) self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$tmp_graph['name'].' ]: cannot inherit. No required items on [ '.$chd_host['host'].' ] (Ymax value item)');
				$ymax_itemid = reset($ymax_itemid);
				$tmp_graph['ymax_itemid'] = $ymax_itemid['itemid'];
			}
			if($tmp_graph['ymin_itemid'] > 0){
				$ymin_itemid = get_same_graphitems_for_host(array(array('itemid' => $tmp_graph['ymin_itemid'])), $chd_host['hostid']);
				if(!$ymin_itemid) self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$tmp_graph['name'].' ]: cannot inherit. No required items on [ '.$chd_host['host'].' ] (Ymin value item)');
				$ymin_itemid = reset($ymin_itemid);
				$tmp_graph['ymin_itemid'] = $ymin_itemid['itemid'];
			}

// check if templated graph exists
			$chd_graphs = self::get(array(
				'filter' => array('templateid' => $tmp_graph['graphid'], 'flags' => array(ZBX_FLAG_DISCOVERY_CHILD, ZBX_FLAG_DISCOVERY_NORMAL)),
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1,
				'hostids' => $chd_host['hostid']
			));

			if($chd_graph = reset($chd_graphs)){
				if((zbx_strtolower($tmp_graph['name']) != zbx_strtolower($chd_graph['name']))
						&& self::exists(array('name' => $tmp_graph['name'], 'hostids' => $chd_host['hostid'])))
				{
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$tmp_graph['name'].' ]: already exists on [ '.$chd_host['host'].' ]');
				}
				else if($chd_graph['flags'] != $tmp_graph['flags']){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph with same name but other type exist');
				}

				$tmp_graph['graphid'] = $chd_graph['graphid'];
				self::updateReal($tmp_graph);
			}
// check if graph with same name and items exists
			else{
				$options = array(
					'filter' => array('name' => $tmp_graph['name'], 'flags' => null),
					'output' => API_OUTPUT_EXTEND,
					'preservekeys' => 1,
					'nopermissions' => 1,
					'hostids' => $chd_host['hostid']
				);
				$chd_graph = self::get($options);
				if($chd_graph = reset($chd_graph)){
					if($chd_graph['templateid'] != 0){
						self::exception(ZBX_API_ERROR_PARAMETERS, S_GRAPH.' [ '.$tmp_graph['name'].' ]: already exists on [ '.$chd_host['host'].' ] (inherited from another template)');
					}
					else if($chd_graph['flags'] != $tmp_graph['flags']){
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph with same name but other type exist');
					}

					$options = array(
						'graphids' => $chd_graph['graphid'],
						'output' => API_OUTPUT_EXTEND,
						'preservekeys' => 1,
						'expandData' => 1,
						'nopermissions' => 1
					);
					$chd_graph_items = CGraphItem::get($options);

					if(count($chd_graph_items) == count($tmp_graph['gitems'])){
						foreach($tmp_graph['gitems'] as $gitem){
							foreach($chd_graph_items as $chd_item){
								if(($gitem['key_'] == $chd_item['key_']) && (bccomp($chd_host['hostid'], $chd_item['hostid']) == 0))
									continue 2;
							}

							self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$tmp_graph['name'].' ]: already exists on [ '.$chd_host['host'].' ] (items are not identical)');
						}

						$tmp_graph['graphid'] = $chd_graph['graphid'];
						self::updateReal($tmp_graph);
					}
					else{
						self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph [ '.$tmp_graph['name'].' ]: already exists on [ '.$chd_host['host'].' ] (items are not identical)');
					}
				}
				else{
					$graphid = self::createReal($tmp_graph);
					$tmp_graph['graphid'] = $graphid;
				}
			}
			self::inherit($tmp_graph);
		}
	}

/**
 * Inherit template graphs from template to host
 *
 * @param array $data
 * @return boolean
 */
	public static function syncTemplates($data){
		try{
			self::BeginTransaction(__METHOD__);

			$data['templateids'] = zbx_toArray($data['templateids']);
			$data['hostids'] = zbx_toArray($data['hostids']);

			$options = array(
				'hostids' => $data['hostids'],
				'editable' => 1,
				'preservekeys' => 1,
				'templated_hosts' => 1,
				'output' => API_OUTPUT_SHORTEN
			);
			$allowedHosts = CHost::get($options);
			foreach($data['hostids'] as $hostid){
				if(!isset($allowedHosts[$hostid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}
			$options = array(
				'templateids' => $data['templateids'],
				'preservekeys' => 1,
				'output' => API_OUTPUT_SHORTEN
			);
			$allowedTemplates = CTemplate::get($options);
			foreach($data['templateids'] as $templateid){
				if(!isset($allowedTemplates[$templateid])){
					self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}

			$sql = 'SELECT hostid, templateid'.
				' FROM hosts_templates'.
				' WHERE '.DBcondition('hostid', $data['hostids']).
				' AND '.DBcondition('templateid', $data['templateids']);
			$db_links = DBSelect($sql);
			$linkage = array();
			while($link = DBfetch($db_links)){
				if(!isset($linkage[$link['templateid']])) $linkage[$link['templateid']] = array();
				$linkage[$link['templateid']][$link['hostid']] = 1;
			}

			$options = array(
				'hostids' => $data['templateids'],
				'preservekeys' => 1,
				'output' => API_OUTPUT_EXTEND,
				'select_graph_items' => API_OUTPUT_EXTEND,
			);
			$graphs = self::get($options);

			foreach($graphs as $graph){
				foreach($data['hostids'] as $hostid){
					if(isset($linkage[$graph['hosts'][0]['hostid']][$hostid])){
						self::inherit($graph, $hostid);
					}
				}
			}

			self::EndTransaction(true, __METHOD__);
			return true;
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Delete graphs
 *
 * @param array $graphs
 * @param array $graphs['graphids']
 * @return boolean
 */
	public static function delete($graphids, $nopermissions=false){
		$graphids = zbx_toArray($graphids);
		if(empty($graphids)) return true;

		try{
			self::BeginTransaction(__METHOD__);

// TODO: remove $nopermissions hack
			$options = array(
				'graphids' => $graphids,
				'editable' => 1,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1
			);
			$del_graphs = self::get($options);

			if(!$nopermissions){
				foreach($graphids as $graphid){
					if(!isset($del_graphs[$graphid]))
						self::exception(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
					if($del_graphs[$graphid]['templateid'] != 0){
						self::exception(ZBX_API_ERROR_PERMISSIONS, 'Cannot delete templated graphs');
					}
				}
			}

			$parent_graphids = $graphids;
			do{
				$db_graphs = DBselect('SELECT graphid FROM graphs WHERE ' . DBcondition('templateid', $parent_graphids));
				$parent_graphids = array();
				while($db_graph = DBfetch($db_graphs)){
					$parent_graphids[] = $db_graph['graphid'];
					$itemids[$db_graph['graphid']] = $db_graph['graphid'];
				}
			} while(!empty($parent_graphids));

			DB::delete('screens_items', array(
				'resourceid'=>$graphids,
				'resourcetype'=>SCREEN_RESOURCE_GRAPH
			));

			DB::delete('profiles', array(
				'idx'=>'web.favorite.graphids',
				'source'=>'graphid',
				'value_id'=>$graphids
			));

			DB::delete('graphs', array(
				'graphid'=> $graphids
			));

// TODO: REMOVE info
			foreach($del_graphs as $graphid => $graph){
				info(_s('Graph [%s] deleted.', $graph['name']));
			}

			self::EndTransaction(true, __METHOD__);
			return true;
		}
		catch(APIException $e){
			self::EndTransaction(false, __METHOD__);
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

	private static function checkInput($graphs, $update=false){
		$itemids = array();

		foreach($graphs as $gnum => $graph){
// EXCEPTION: GRAPH FIELDS {{{
			$fields = array('name' => null);
			if(!$update && !check_db_fields($fields, $graph)){
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for graph');
			}
// }}} EXCEPTION: GRAPH FIELDS

// EXCEPTION: NO ITEMS {{{
			if(!isset($graph['gitems']) || !is_array($graph['gitems']) || empty($graph['gitems'])){
				self::exception(ZBX_API_ERROR_PARAMETERS, S_MISSING_ITEMS_FOR_GRAPH.' [ '.$graph['name'].' ]');
			}
// }}} EXCEPTION: NO ITEMS

// EXCEPTION: ITEMS FIELDS {{{
			$fields = array('itemid' => null);
			foreach($graph['gitems'] as $ginum => $gitem){
				if(!check_db_fields($fields, $gitem)){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Wrong fields for items');
				}
			}
// }}} EXCEPTION: ITEMS FIELDS

// EXCPETION: more than one sum type item for pie graph {{{
			if(($graph['graphtype'] == GRAPH_TYPE_PIE) || ($graph['graphtype'] == GRAPH_TYPE_EXPLODED)){
				$sum_items = 0;
				foreach($graph['gitems'] as $gitem){
					if($gitem['type'] == GRAPH_ITEM_SUM) $sum_items++;
				}
				if($sum_items > 1) self::exception(ZBX_API_ERROR_PARAMETERS, S_ANOTHER_ITEM_SUM.' [ '.$graph['name'].' ]');
			}
// }}} EXCEPTION

			$itemids += zbx_objectValues($graph['gitems'], 'itemid');
		}


		if(!empty($itemids)){
// EXCEPTION: ITEMS PERMISSIONS {{{
			$options = array(
				'nodeids' => get_current_nodeid(true),
				'itemids' => array_unique($itemids),
				'webitems' => 1,
				'editable' => 1,
				'output' => API_OUTPUT_EXTEND,
				'preservekeys' => 1,
			);
			$allowed_items = CItem::get($options);

			foreach($itemids as $inum => $itemid){
				if(!isset($allowed_items[$itemid])){
					self::exception(ZBX_API_ERROR_PARAMETERS, S_NO_PERMISSIONS);
				}
			}
// }}} EXCEPTION: ITEMS PERMISSIONS
		}


		foreach($graphs as $gnum => $graph){
			if(!isset($graph['name'])) continue;

			$options = array(
				'nodeids' => get_current_nodeid(true),
				'output' => API_OUTPUT_SHORTEN,
				'filter' => array('name' => $graph['name'], 'flags' => null),
				'itemids' => zbx_objectValues($graph['gitems'], 'itemid'),
				'nopermissions' => 1
			);
			$graphsExists = self::get($options);
			foreach($graphsExists as $genum => $graphExists){
				if(($update && ($graphExists['graphid'] != $graph['graphid'])) || !$update){
					self::exception(ZBX_API_ERROR_PARAMETERS, 'Graph with name [ '.$graph['name'].' ] already exists');
				}
			}
// }}} EXCEPTION: GRAPH EXISTS
		}

		return true;
	}

	protected static function checkAxisItems($graph, $tpl=false){

		$axis_items = array();
		if(isset($graph['ymin_type']) && ($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE)){
			$axis_items[$graph['ymin_itemid']] = $graph['ymin_itemid'];
		}
		if(isset($graph['ymax_type']) && $graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE){
			$axis_items[$graph['ymax_itemid']] = $graph['ymax_itemid'];
		}

		if(!empty($axis_items)){
			$cnt = count($axis_items);

			$options = array(
				'itemids' => $axis_items,
				'output' => API_OUTPUT_SHORTEN,
				'countOutput' => 1,
			);
			if($tpl)
				$options['hostids'] = $tpl;
			else
				$options['templated'] = false;

			$cnt_exist = CItem::get($options);

			if($cnt != $cnt_exist)
				self::exception(ZBX_API_ERROR_PARAMETERS, 'Incorrect item for axis value item');
		}

		return true;
	}
}
?>
