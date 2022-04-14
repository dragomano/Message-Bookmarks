<?php

function template_bookmark()
{
	global $scripturl, $context, $txt;

	echo '
	<form action="', $scripturl, '?action=mb;sa=', isset($_REQUEST['sa']) && $_REQUEST['sa'] === 'edit' ? 'edit' : 'add', '" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', $context['page_title'], '</h3>
		</div>
		<div class="windowbg">
			<div class="content">
				<dl class="settings">
					<dt>
						<label for="title"><strong>', $txt['mb_subject'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" id="title" name="title" maxlength="255" class="input_text" value="', $context['mb_title'], '" style="width: 100%">
					</dd>
					<dt>
						<label for="note"><strong>', $txt['mb_note'], ':</strong></label>
					</dt>
					<dd>
						<textarea id="note" name="note" rows="5" cols="20">', $context['mb_note'], '</textarea>
					</dd>
				</dl>
				<div class="righttext">
					<input type="submit" name="make_bookmark" value="', $txt['mb_add_bookmark'], '" class="button">
				</div>
			</div>
		</div>';

	foreach ($context['form_hidden_vars'] as $key => $value) {
		echo '
		<input type="hidden" name="', $key, '" value="', $value, '">';
	}

	echo '
	</form>';
}
