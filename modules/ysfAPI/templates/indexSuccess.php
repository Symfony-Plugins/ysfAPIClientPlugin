<div class="yui-gc">
  <div class="yui-u first">
  <h2><r3:trans>Welcome!</r3:trans></h2>

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
