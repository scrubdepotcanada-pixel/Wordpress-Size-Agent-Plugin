(function () {
  /**
   * Mount the correct agent widget into the container.
   * config.agentType determines which global object to use:
   *   'shoes'  → window.NursingShoesSizeAgent.mount()
   *   'scrubs' → window.ScrubsSizeAgent.mount()  (default)
   */
  function getAgentObject(agentType) {
    if (agentType === 'shoes') {
      return window.NursingShoesSizeAgent || null;
    }
    return window.ScrubsSizeAgent || null;
  }

  function mountIntoContainer(containerId, config, attempt) {
    var container = document.getElementById(containerId);
    if (!container) {
      return;
    }

    // If already mounted by either polling or event, bail out
    if (container.getAttribute('data-size-agent-mounted') === 'true') {
      return;
    }

    var agentType = (config && config.agentType) ? config.agentType : 'scrubs';
    var agent = getAgentObject(agentType);

    if (agent && typeof agent.mount === 'function') {
      container.setAttribute('data-size-agent-mounted', 'true');
      agent.mount(container, config || {});
      return;
    }

    // On first attempt, also listen for SizeAgentReady event as a fast path
    if ((attempt || 0) === 0) {
      window.addEventListener('SizeAgentReady', function handler() {
        window.removeEventListener('SizeAgentReady', handler);
        mountIntoContainer(containerId, config, 0);
      });
    }

    if ((attempt || 0) >= 80) {
      // Only show error if nothing has mounted yet
      if (container.getAttribute('data-size-agent-mounted') !== 'true') {
        container.innerHTML =
          '<div class="size-agent-status is-error">Size widget is temporarily unavailable.</div>';
      }
      return;
    }

    window.setTimeout(function () {
      mountIntoContainer(containerId, config, (attempt || 0) + 1);
    }, 250);
  }

  function processQueue() {
    var queue = window.SizeAgentMountQueue || [];
    if (!queue.length) {
      return;
    }
    while (queue.length) {
      var item = queue.shift();
      mountIntoContainer(item.containerId, item.config, 0);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', processQueue);
  } else {
    processQueue();
  }
})();
