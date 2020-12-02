<?php
return [
  'update' => false,
  'showDiff' => false,
  'insidePageTree' =>
  [
    0 =>
    [
      'ref' => 'pages:1',
      'type' => 'record',
      'preCode' => '<span title="pages:1"><span class="t3js-icon icon icon-size-small icon-state-default icon-apps-pagetree-page-default" data-identifier="apps-pagetree-page-default">
	<span class="icon-markup">
<svg class="icon-color" role="img"><use xlink:href="typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/apps.svg#apps-pagetree-page-default" /></svg>
	</span>
	
</span></span>',
      'title' => 'Root',
      'active' => 'active',
      'controls' => '',
      'message' => '',
    ],
    1 =>
    [
      'ref' => 'tt_content:1',
      'type' => 'record',
      'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;<span title="tt_content:1"><span class="t3js-icon icon icon-size-small icon-state-default icon-mimetypes-x-content-text" data-identifier="mimetypes-x-content-text">
	<span class="icon-markup">
<svg class="icon-color" role="img"><use xlink:href="typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/mimetypes.svg#mimetypes-x-content-text" /></svg>
	</span>
	
</span></span>',
      'title' => 'Test content',
      'active' => 'active',
      'controls' => '',
      'message' => '',
    ],
    2 =>
    [
      'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="t3js-icon icon icon-size-small icon-state-default icon-status-reference-soft" data-identifier="status-reference-soft">
	<span class="icon-markup">
<img src="typo3/sysext/impexp/Resources/Public/Icons/status-reference-soft.png" width="16" height="16" alt="" />
	</span>
	
</span>',
      'title' => '<em>header_link, "typolink" </em>: <span title="file:2">file:2</span><br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Record <strong>sys_file:2</strong>',
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
      'controls' => '',
      'message' => '',
    ],
    3 =>
    [
      'ref' => 'sys_file:2',
      'title' => '<span title="/">typo3_image3.jpg</span>',
      'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="" title="sys_file:2"><span class="t3js-icon icon icon-size-small icon-state-default icon-status-status-checked" data-identifier="status-status-checked">
	<span class="icon-markup">
<span class="icon-unify"><i class="fa fa-check"></i></span>
	</span>
	
</span></span>',
      'type' => 'rel',
      'controls' => '',
      'message' => '',
    ],
    4 =>
    [
      'ref' => 'sys_file_storage:1',
      'title' => '<span title="/">sys_file_storage:1</span>',
      'msg' => 'LOST RELATION (Record not found!)',
      'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-danger" title="sys_file_storage:1"><span class="t3js-icon icon icon-size-small icon-state-default icon-status-dialog-warning" data-identifier="status-dialog-warning">
	<span class="icon-markup">
<span class="icon-unify"><i class="fa fa-exclamation-triangle"></i></span>
	</span>
	
</span></span>',
      'type' => 'rel',
      'controls' => '',
      'message' => '<span class="text-danger">LOST RELATION (Record not found!)</span>',
    ],
    5 =>
    [
      'ref' => 'tt_content:2',
      'type' => 'record',
      'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;<span title="tt_content:2"><span class="t3js-icon icon icon-size-small icon-state-default icon-mimetypes-x-content-text" data-identifier="mimetypes-x-content-text">
	<span class="icon-markup">
<svg class="icon-color" role="img"><use xlink:href="typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/mimetypes.svg#mimetypes-x-content-text" /></svg>
	</span>
	
</span></span>',
      'title' => 'Test content 2',
      'active' => 'active',
      'controls' => '',
      'message' => '',
    ],
    6 =>
    [
      'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="t3js-icon icon icon-size-small icon-state-default icon-status-reference-soft" data-identifier="status-reference-soft">
	<span class="icon-markup">
<img src="typo3/sysext/impexp/Resources/Public/Icons/status-reference-soft.png" width="16" height="16" alt="" />
	</span>
	
</span>',
      'title' => '<em>header_link, "typolink" </em>: <span title="file:4">file:4</span><br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Record <strong>sys_file:4</strong>',
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
      'controls' => '',
      'message' => '',
    ],
    7 =>
    [
      'ref' => 'sys_file:4',
      'title' => '<span title="/">Empty.html</span>',
      'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="" title="sys_file:4"><span class="t3js-icon icon icon-size-small icon-state-default icon-status-status-checked" data-identifier="status-status-checked">
	<span class="icon-markup">
<span class="icon-unify"><i class="fa fa-check"></i></span>
	</span>
	
</span></span>',
      'type' => 'rel',
      'controls' => '',
      'message' => '',
    ],
    8 =>
    [
      'ref' => 'pages:2',
      'type' => 'record',
      'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;<span title="pages:2"><span class="t3js-icon icon icon-size-small icon-state-default icon-apps-pagetree-page-default" data-identifier="apps-pagetree-page-default">
	<span class="icon-markup">
<svg class="icon-color" role="img"><use xlink:href="typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/apps.svg#apps-pagetree-page-default" /></svg>
	</span>
	
</span></span>',
      'title' => 'Dummy 1-2',
      'active' => 'active',
      'controls' => '',
      'message' => '',
    ],
  ],
  'outsidePageTree' =>
  [
    0 =>
    [
      'ref' => 'sys_file:2',
      'type' => 'record',
      'preCode' => '<span title="sys_file:2"><span class="t3js-icon icon icon-size-small icon-state-default icon-mimetypes-media-image" data-identifier="mimetypes-media-image">
	<span class="icon-markup">
<svg class="icon-color" role="img"><use xlink:href="typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/mimetypes.svg#mimetypes-media-image" /></svg>
	</span>
	
</span></span>',
      'title' => 'typo3_image3.jpg',
      'active' => 'active',
      'msg' => 'TABLE \'sys_file\' will be inserted on ROOT LEVEL! ',
      'controls' => '',
      'message' => '<span class="text-danger">TABLE \'sys_file\' will be inserted on ROOT LEVEL! </span>',
    ],
    1 =>
    [
      'ref' => 'sys_file_storage:1',
      'title' => '<span title="/">sys_file_storage:1</span>',
      'msg' => 'LOST RELATION (Record not found!)',
      'preCode' => '&nbsp;&nbsp;&nbsp;&nbsp;<span class="text-danger" title="sys_file_storage:1"><span class="t3js-icon icon icon-size-small icon-state-default icon-status-dialog-warning" data-identifier="status-dialog-warning">
	<span class="icon-markup">
<span class="icon-unify"><i class="fa fa-exclamation-triangle"></i></span>
	</span>
	
</span></span>',
      'type' => 'rel',
      'controls' => '',
      'message' => '<span class="text-danger">LOST RELATION (Record not found!)</span>',
    ],
    2 =>
    [
      'ref' => 'sys_file:4',
      'type' => 'record',
      'preCode' => '<span title="sys_file:4"><span class="t3js-icon icon icon-size-small icon-state-default icon-mimetypes-text-text" data-identifier="mimetypes-text-text">
	<span class="icon-markup">
<svg class="icon-color" role="img"><use xlink:href="typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/mimetypes.svg#mimetypes-text-text" /></svg>
	</span>
	
</span></span>',
      'title' => 'Empty.html',
      'active' => 'active',
      'msg' => 'TABLE \'sys_file\' will be inserted on ROOT LEVEL! ',
      'controls' => '',
      'message' => '<span class="text-danger">TABLE \'sys_file\' will be inserted on ROOT LEVEL! </span>',
    ],
  ],
];
