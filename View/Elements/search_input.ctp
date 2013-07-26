<?php
/**
 * @param mixed $url: 'url' option for Form::create(), defaults to array()
 * @param string $id: form tag's id, defaults to 'search-form'
 * @param string $class: form tag's class, defaults to 'search-form'
 */

	$options = array(
		'url' => empty($url) ? array() : $url,
		'id' => empty($id) ? 'search-form' : $id,
		'class' => empty($class) ? 'search-form' : $class,
	);
?>

<?php echo $this->Form->create(false, $options); ?>
	<?php echo $this->Form->input('search', array(
		'id' => 'search-input',
		'class' => 'search-input',
		'label' => false,
		'div' => false,
		'before' => false,
		'between' => false,
		'after' => false,
	)); ?>
<?php echo $this->Form->end(); ?>