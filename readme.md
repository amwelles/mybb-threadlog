# Accessing the threadlog

In case you lose the link for some reason, threadlogs can be accessed with the following link (sub UID with the user ID you'd like to view):

	<a href="member.php?action=profile&show=threadlog&uid=UID">Click me!</a>

# Template Variables

## threadlog

<dl>
	<dt>{$threads}</dt>
	<dd>outputs either <code>threadlog_row</code> or <code>threadlog_nothreads</code></dd>
</dl>

## threadlog_row

<dl>
	<dt>{$class}</dt>
	<dd>outputs "archived", "active", or "dead"</dd>
	<dt>{$threadlink}</dt>
	<dd>outputs an anchor tag with the thread subject</dd>
	<dt>{$lastpostdate}</dt>
	<dd>outputs date of last post in the thread (format modified by configuration -&gt; date and time -&gt; date format)</dd>
	<dt>{$lastposter}</dt>
	<dd>outputs plain text version of the last user to post</dd>
</dl>

## threadlog_nothreads

This template has no variables.