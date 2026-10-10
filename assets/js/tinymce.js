/**
 * TinyMCE editor behaviour.
 * Loaded through mce_external_plugins. Not part of the JS build.
 */
(function () {
	tinymce.PluginManager.add('cs_tinymce', function (editor) {
		addButtons(editor);
	});

	/* --- [cs-button] menu --- */
	function addButtons(editor) {
		function insertButton(attrs) {
			var label = editor.selection.getContent({ format: 'text' }) || 'Button';
			label = label.replace(/[\[\]]/g, '');
			editor.insertContent('[cs-button' + attrs + ']' + label + '[/cs-button]');
		}

		editor.addButton('cs_buttons', {
			type: 'menubutton',
			icon: 'link',
			tooltip: 'Buttons',
			menu: [
				{
					text: 'Default button',
					onclick: function () {
						insertButton(' url="" target=""');
					}
				},
				{
					text: 'Default button, White',
					onclick: function () {
						insertButton(' url="" target="" color="white"');
					}
				},
				{
					text: 'Outlined button',
					onclick: function () {
						insertButton(' url="" target="" style="outline"');
					}
				},
				{
					text: 'Outlined button, White',
					onclick: function () {
						insertButton(' url="" target="" style="outline" color="white"');
					}
				}
			]
		});
	}
})();