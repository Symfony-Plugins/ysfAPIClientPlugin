<div class="yui-gc">
  <div class="yui-u first">
  <h2 style="margin-bottom: 20px">Find something:</h2>

  <form action="<?php echo url_for('ysfAPI/search'); ?>" method="post">
    <?php echo $form; ?>
    <input type="submit" value="go" />
  </form>

  <?php if(isset($results) && !empty($results)): ?>
    <h2>Results for "<?php echo $query ?>":</h2>
    <ol>
      <?php $uniques = array(); foreach ($results as $result): if(!in_array($result->title, $uniques)): $uniques[] = $result->title; ?>
      <li>
      <h4><a href="<?php echo $result->url; ?>"><?php echo $result->title; ?></a></h4>
      <blockquote><?php echo $result->abstract; ?></blockquote>

      </li>
      <?php endif; endforeach; ?>
    </ol>
   <?php endif; ?>

  </div>
  <div class="yui-u">
  </div>
 </div>
</div>
