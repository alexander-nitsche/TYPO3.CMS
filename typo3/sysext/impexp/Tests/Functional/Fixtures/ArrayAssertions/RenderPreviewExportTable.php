<?php
return [
  'update' => false,
  'showDiff' => false,
  'pagetreeLines' =>
  [
  ],
  'remainingRecords' =>
  [
    0 =>
    [
      'ref' => 'tt_content:1',
      'active' => 'active',
      'preCode' => '<span title="tt_content:1"><span class="t3js-icon icon icon-size-small icon-state-default icon-mimetypes-x-content-text" data-identifier="mimetypes-x-content-text">
	<span class="icon-markup">
<svg class="icon-color" role="img"><use xlink:href="typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/mimetypes.svg#mimetypes-x-content-text" /></svg>
	</span>
	
</span></span>',
      'title' => 'Test content',
      'type' => 'record',
      'controls' => '<input type="checkbox" class="t3js-exclude-checkbox" name="tx_impexp[exclude][tt_content:1]" id="checkExcludett_content:1" value="1" /> <label for="checkExcludett_content:1"></label>',
      'message' => '',
    ],
    1 =>
    [
      'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;<span class="t3js-icon icon icon-size-small icon-state-default icon-default-not-found" data-identifier="default-not-found">
	<span class="icon-markup">
<svg class="icon-color" role="img"><use xlink:href="typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/default.svg#default-not-found" /></svg>
	</span>
	
</span>',
      'title' => '<em>header_link, "typolink" </em>: <span title="file:2">file:2</span><br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <strong>sys_file:2</strong>',
      'ref' => 'SOFTREF',
      'type' => 'softref',
      '_softRefInfo' =>
      [
        'field' => 'header_link',
        'spKey' => 'typolink',
        'matchString' => 'file:2',
        'subst' =>
        [
          'type' => 'db',
          'recordRef' => 'sys_file:2',
          'tokenID' => '2487ce518ed56d22f20f259928ff43f1',
          'tokenValue' => 'file:2',
        ],
      ],
      'controls' => '<select name="tx_impexp[softrefCfg][2487ce518ed56d22f20f259928ff43f1][mode]"><option value="" selected="selected"></option><option value="editable"></option><option value="exclude"></option></select><br/>',
      'message' => '',
    ],
    2 =>
    [
      'ref' => 'sys_file:2',
      'title' => '<span title="/">sys_file:2</span>',
      'msg' => 'LOST RELATION (Path: /)',
      'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-danger" title="sys_file:2"><span class="t3js-icon icon icon-size-small icon-state-default icon-status-dialog-warning" data-identifier="status-dialog-warning">
	<span class="icon-markup">
<span class="icon-unify"><i class="fa fa-exclamation-triangle"></i></span>
	</span>
	
</span></span>',
      'type' => 'rel',
      'controls' => '',
      'message' => '<span class="text-danger">LOST RELATION (Path: /)</span>',
    ],
    3 =>
    [
      'ref' => 'tt_content:2',
      'active' => 'active',
      'preCode' => '<span title="tt_content:2"><span class="t3js-icon icon icon-size-small icon-state-default icon-mimetypes-x-content-text" data-identifier="mimetypes-x-content-text">
	<span class="icon-markup">
<svg class="icon-color" role="img"><use xlink:href="typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/mimetypes.svg#mimetypes-x-content-text" /></svg>
	</span>
	
</span></span>',
      'title' => 'Test content 2',
      'type' => 'record',
      'controls' => '<input type="checkbox" class="t3js-exclude-checkbox" name="tx_impexp[exclude][tt_content:2]" id="checkExcludett_content:2" value="1" /> <label for="checkExcludett_content:2"></label>',
      'message' => '',
    ],
    4 =>
    [
      'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;<span class="t3js-icon icon icon-size-small icon-state-default icon-default-not-found" data-identifier="default-not-found">
	<span class="icon-markup">
<svg class="icon-color" role="img"><use xlink:href="typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/default.svg#default-not-found" /></svg>
	</span>
	
</span>',
      'title' => '<em>header_link, "typolink" </em>: <span title="file:4">file:4</span><br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <strong>sys_file:4</strong>',
      'ref' => 'SOFTREF',
      'type' => 'softref',
      '_softRefInfo' =>
      [
        'field' => 'header_link',
        'spKey' => 'typolink',
        'matchString' => 'file:4',
        'subst' =>
        [
          'type' => 'db',
          'recordRef' => 'sys_file:4',
          'tokenID' => '81b8b33df54ef433f1cbc7c3e513e6c4',
          'tokenValue' => 'file:4',
        ],
      ],
      'controls' => '<select name="tx_impexp[softrefCfg][81b8b33df54ef433f1cbc7c3e513e6c4][mode]"><option value="" selected="selected"></option><option value="editable"></option><option value="exclude"></option></select><br/>',
      'message' => '',
    ],
    5 =>
    [
      'ref' => 'sys_file:4',
      'title' => '<span title="/">sys_file:4</span>',
      'msg' => 'LOST RELATION (Path: /)',
      'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-danger" title="sys_file:4"><span class="t3js-icon icon icon-size-small icon-state-default icon-status-dialog-warning" data-identifier="status-dialog-warning">
	<span class="icon-markup">
<span class="icon-unify"><i class="fa fa-exclamation-triangle"></i></span>
	</span>
	
</span></span>',
      'type' => 'rel',
      'controls' => '',
      'message' => '<span class="text-danger">LOST RELATION (Path: /)</span>',
    ],
    6 =>
    [
      'ref' => 'tt_content:3',
      'active' => 'hidden',
      'preCode' => '<span title="tt_content:3"><span class="t3js-icon icon icon-size-small icon-state-default icon-mimetypes-x-content-text" data-identifier="mimetypes-x-content-text">
	<span class="icon-markup">
<svg class="icon-color" role="img"><use xlink:href="typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/mimetypes.svg#mimetypes-x-content-text" /></svg>
	</span>
	<span class="icon-overlay icon-overlay-hidden"><svg class="icon-color" role="img"><use xlink:href="typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/overlay.svg#overlay-hidden" /></svg></span>
</span></span>',
      'title' => 'Test content 3',
      'type' => 'record',
      'controls' => '<input type="checkbox" class="t3js-exclude-checkbox" name="tx_impexp[exclude][tt_content:3]" id="checkExcludett_content:3" value="1" /> <label for="checkExcludett_content:3"></label>',
      'message' => '',
    ],
    7 =>
    [
      'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;<span class="t3js-icon icon icon-size-small icon-state-default icon-default-not-found" data-identifier="default-not-found">
	<span class="icon-markup">
<svg class="icon-color" role="img"><use xlink:href="typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/default.svg#default-not-found" /></svg>
	</span>
	
</span>',
      'title' => '<em>header_link, "typolink" </em>: <span title="file:3">file:3</span><br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <strong>sys_file:3</strong>',
      'ref' => 'SOFTREF',
      'type' => 'softref',
      '_softRefInfo' =>
      [
        'field' => 'header_link',
        'spKey' => 'typolink',
        'matchString' => 'file:3',
        'subst' =>
        [
          'type' => 'db',
          'recordRef' => 'sys_file:3',
          'tokenID' => '0b1253ebf70ef5be862f29305e404edc',
          'tokenValue' => 'file:3',
        ],
      ],
      'controls' => '<select name="tx_impexp[softrefCfg][0b1253ebf70ef5be862f29305e404edc][mode]"><option value="" selected="selected"></option><option value="editable"></option><option value="exclude"></option></select><br/>',
      'message' => '',
    ],
    8 =>
    [
      'ref' => 'sys_file:3',
      'title' => '<span title="/">sys_file:3</span>',
      'msg' => 'LOST RELATION (Path: /)',
      'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-danger" title="sys_file:3"><span class="t3js-icon icon icon-size-small icon-state-default icon-status-dialog-warning" data-identifier="status-dialog-warning">
	<span class="icon-markup">
<span class="icon-unify"><i class="fa fa-exclamation-triangle"></i></span>
	</span>
	
</span></span>',
      'type' => 'rel',
      'controls' => '',
      'message' => '<span class="text-danger">LOST RELATION (Path: /)</span>',
    ],
  ],
];
