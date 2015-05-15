<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/js/configuration.item.list.js.php';

$itemsWidget = (new CWidget('item-list'))->setTitle(_('Items'));

// create new item button
$createForm = (new CForm('get'))->cleanItems();
$controls = new CList();

if (empty($this->data['hostid'])) {
	$createButton = new CSubmit('form', _('Create item (select host first)'));
	$createButton->setEnabled(false);
	$controls->addItem($createButton);
}
else {
	$createForm->addVar('hostid', $this->data['hostid']);
	$controls->addItem(new CSubmit('form', _('Create item')));
}
$createForm->addItem($controls);
$itemsWidget->setControls($createForm);

if (!empty($this->data['hostid'])) {
	$itemsWidget->addItem(get_header_host_table('items', $this->data['hostid']));
}
$itemsWidget->addItem($this->data['flicker']);

// create form
$itemForm = new CForm();
$itemForm->setName('items');
if (!empty($this->data['hostid'])) {
	$itemForm->addVar('hostid', $this->data['hostid']);
}

// create table
$itemTable = new CTableInfo(
	($this->data['filterSet']) ? null : _('Specify some filter condition to see the items.')
);
$itemTable->setHeader(array(
	new CColHeader(
		new CCheckBox('all_items', null, "checkAll('".$itemForm->getName()."', 'all_items', 'group_itemid');"),
		'cell-width'),
	_('Wizard'),
	empty($this->data['filter_hostid']) ? _('Host') : null,
	make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
	_('Triggers'),
	make_sorting_header(_('Key'), 'key_', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Interval'), 'delay', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('History'), 'history', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Trends'), 'trends', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Type'), 'type', $this->data['sort'], $this->data['sortorder']),
	_('Applications'),
	make_sorting_header(_('Status'), 'status', $this->data['sort'], $this->data['sortorder']),
	$data['showInfoColumn'] ? _('Info') : null
));

$currentTime = time();

foreach ($this->data['items'] as $item) {
	// description
	$description = array();
	if (!empty($item['template_host'])) {
		$description[] = new CLink(
			CHtml::encode($item['template_host']['name']),
			'?hostid='.$item['template_host']['hostid'].'&filter_set=1',
			ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_GREY
		);
		$description[] = NAME_DELIMITER;
	}

	if (!empty($item['discoveryRule'])) {
		$description[] = new CLink(
			CHtml::encode($item['discoveryRule']['name']),
			'disc_prototypes.php?parent_discoveryid='.$item['discoveryRule']['itemid'],
			ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_ORANGE
		);
		$description[] = NAME_DELIMITER.$item['name_expanded'];
	}
	else {
		$description[] = new CLink(
			CHtml::encode($item['name_expanded']),
			'?form=update&hostid='.$item['hostid'].'&itemid='.$item['itemid']
		);
	}

	// status
	$status = new CCol(new CLink(
		itemIndicator($item['status'], $item['state']),
		'?group_itemid='.$item['itemid'].
			'&hostid='.$item['hostid'].
			'&action='.($item['status'] == ITEM_STATUS_DISABLED ? 'item.massenable' : 'item.massdisable'),
		ZBX_STYLE_LINK_ACTION.' '.itemIndicatorStyle($item['status'], $item['state'])
	));

	// info
	if ($data['showInfoColumn']) {
		$infoIcons = array();

		if ($item['status'] == ITEM_STATUS_ACTIVE && !zbx_empty($item['error'])) {
			$info = new CDiv(SPACE, 'status_icon iconerror');
			$info->setHint($item['error'], ZBX_STYLE_RED);

			$infoIcons[] = $info;
		}

		// discovered item lifetime indicator
		if ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $item['itemDiscovery']['ts_delete']) {
			$deleteError = new CDiv(SPACE, 'status_icon iconwarning');

			// Check if item should've been deleted in the past.
			if ($currentTime > $item['itemDiscovery']['ts_delete']) {
				$deleteError->setHint(_s(
					'The item is not discovered anymore and will be deleted the next time discovery rule is processed.'
				));
			}
			else {
				$deleteError->setHint(_s(
					'The item is not discovered anymore and will be deleted in %1$s (on %2$s at %3$s).',
					zbx_date2age($item['itemDiscovery']['ts_delete']),
					zbx_date2str(DATE_FORMAT, $item['itemDiscovery']['ts_delete']),
					zbx_date2str(TIME_FORMAT, $item['itemDiscovery']['ts_delete'])
				));
			}

			$infoIcons[] = $deleteError;
		}

		if (!$infoIcons) {
			$infoIcons[] = '';
		}
	}
	else {
		$infoIcons = null;
	}

	// triggers info
	$triggerHintTable = new CTableInfo();
	$triggerHintTable->setHeader(array(
		_('Severity'),
		_('Name'),
		_('Expression'),
		_('Status')
	));

	foreach ($item['triggers'] as $num => &$trigger) {
		$trigger = $this->data['itemTriggers'][$trigger['triggerid']];
		$triggerDescription = array();
		if ($trigger['templateid'] > 0) {
			if (!isset($this->data['triggerRealHosts'][$trigger['triggerid']])) {
				$triggerDescription[] = new CSpan('HOST', ZBX_STYLE_GREY);
				$triggerDescription[] = ':';
			}
			else {
				$realHost = reset($this->data['triggerRealHosts'][$trigger['triggerid']]);
				$triggerDescription[] = new CLink(
					CHtml::encode($realHost['name']),
					'triggers.php?hostid='.$realHost['hostid'],
					ZBX_STYLE_GREY
				);
				$triggerDescription[] = ':';
			}
		}

		$trigger['hosts'] = zbx_toHash($trigger['hosts'], 'hostid');

		if ($trigger['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
			$triggerDescription[] = new CSpan(CHtml::encode($trigger['description']));
		}
		else {
			$triggerDescription[] = new CLink(
				CHtml::encode($trigger['description']),
				'triggers.php?form=update&hostid='.key($trigger['hosts']).'&triggerid='.$trigger['triggerid']
			);
		}

		if ($trigger['state'] == TRIGGER_STATE_UNKNOWN) {
			$trigger['error'] = '';
		}

		$trigger['items'] = zbx_toHash($trigger['items'], 'itemid');
		$trigger['functions'] = zbx_toHash($trigger['functions'], 'functionid');

		$triggerHintTable->addRow(array(
			getSeverityCell($trigger['priority'], $this->data['config']),
			$triggerDescription,
			triggerExpression($trigger, true),
			new CSpan(
				triggerIndicator($trigger['status'], $trigger['state']),
				triggerIndicatorStyle($trigger['status'], $trigger['state'])
			),
		));

		$item['triggers'][$num] = $trigger;
	}
	unset($trigger);

	if (!empty($item['triggers'])) {
		$triggerInfo = new CSpan(_('Triggers'), ZBX_STYLE_LINK_ACTION.' link_menu');
		$triggerInfo->setHint($triggerHintTable);
		$triggerInfo = array($triggerInfo);
		$triggerInfo[] = CViewHelper::showNum(count($item['triggers']));

		$triggerHintTable = array();
	}
	else {
		$triggerInfo = SPACE;
	}

	// if item type is 'Log' we must show log menu
	if (in_array($item['value_type'], array(ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_TEXT))) {
		$triggers = array();

		foreach ($item['triggers'] as $trigger) {
			foreach ($trigger['functions'] as $function) {
				if (!str_in_array($function['function'], array('regexp', 'iregexp'))) {
					continue 2;
				}
			}

			$triggers[] = array(
				'id' => $trigger['triggerid'],
				'name' => $trigger['description']
			);
		}

		$menuIcon = new CIcon(_('Menu'), 'iconmenu_b');
		$menuIcon->setMenuPopup(CMenuPopupHelper::getTriggerLog($item['itemid'], $item['name'], $triggers));
	}
	else {
		$menuIcon = SPACE;
	}

	$checkBox = new CCheckBox('group_itemid['.$item['itemid'].']', null, null, $item['itemid']);
	$checkBox->setEnabled(empty($item['discoveryRule']));

	$itemTable->addRow(array(
		$checkBox,
		$menuIcon,
		empty($this->data['filter_hostid']) ? $item['host'] : null,
		$description,
		$triggerInfo,
		CHtml::encode($item['key_']),
		$item['type'] == ITEM_TYPE_TRAPPER || $item['type'] == ITEM_TYPE_SNMPTRAP ? '' : convertUnitsS($item['delay']),
		convertUnitsS(24*3600*$item['history']),
		in_array($item['value_type'], array(ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT)) ? '' : convertUnitsS(24*3600*$item['trends']),
		item_type2str($item['type']),
		new CCol(CHtml::encode($item['applications_list']), 'wraptext'),
		$status,
		$infoIcons
	));
}

zbx_add_post_js('cookie.prefix = "'.$this->data['hostid'].'";');

// append table to form
$itemForm->addItem(array(
	$itemTable,
	$this->data['paging'],
	new CActionButtonList('action', 'group_itemid',
		array(
			'item.massenable' => array('name' => _('Enable'), 'confirm' => _('Enable selected items?')),
			'item.massdisable' => array('name' => _('Disable'), 'confirm' => _('Disable selected items?')),
			'item.massclearhistory' => array('name' => _('Clear history'),
				'confirm' => _('Delete history of selected items?')
			),
			'item.masscopyto' => array('name' => _('Copy')),
			'item.massupdateform' => array('name' => _('Mass update')),
			'item.massdelete' => array('name' => _('Delete'), 'confirm' => _('Delete selected items?'))
		),
		$this->data['hostid']
	)
));

// append form to widget
$itemsWidget->addItem($itemForm);

return $itemsWidget;
