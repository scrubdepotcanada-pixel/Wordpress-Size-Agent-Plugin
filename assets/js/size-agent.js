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

    var agentType = (config && config.agentType) ? config.agentType : 'scrubs';
    var agent = getAgentObject(agentType);

    if (agent && typeof agent.mount === 'function') {
      agent.mount(container, config || {});
      return;
    }

    if ((attempt || 0) >= 40) {
      container.innerHTML =
        '<div class="size-agent-status is-error">Size widget is temporarily unavailable.</div>';
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
