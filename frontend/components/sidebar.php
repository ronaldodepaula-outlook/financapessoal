<aside class="sidebar" id="sidebar">
  <a class="brand" href="<?= e($base) ?>/?page=dashboard"><span class="brand-mark"><i></i><i></i><i></i></span>finança<span class="brand-dot">.</span></a>
  <div class="workspace"><span class="workspace-avatar">P</span><div><strong>Meu espaço pessoal</strong><small>Organização financeira</small></div></div>
  <nav aria-label="Navegação principal">
  <?php $group='';foreach($pages as$key=>$item):if($group!==$item['group']):$group=$item['group']; ?><p class="nav-group"><?= e($group) ?></p><?php endif; ?>
    <a class="nav-item <?= $key===$page?'active':'' ?>" href="<?= e($base) ?>/?page=<?= e($key) ?>" <?= $key===$page?'aria-current="page"':'' ?>><?= icon($item['icon']) ?><span><?= e($item['title']) ?></span><?php if($key===$page): ?><span class="active-dot"></span><?php endif; ?></a>
  <?php endforeach; ?>
  </nav>
  <div class="sidebar-bottom"><span class="status-dot"></span>Seu dinheiro, sob seu controle</div>
</aside>
<button class="sidebar-backdrop" id="sidebar-backdrop" aria-label="Fechar menu"></button>
