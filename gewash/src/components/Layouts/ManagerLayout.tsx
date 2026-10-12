import { useEffect } from "react"
import ManagerNavBar from "../../components/pages/Manager/ManagerNavBar"
import { Outlet, useLocation } from "react-router-dom"
import { scrollForLocation } from "@/lib/scrollForLocation"

function ManagerLayout() {
  const { pathname, hash } = useLocation()
  useEffect(() => {
    scrollForLocation(hash)
  }, [pathname, hash])

  return (
    <>
      <Outlet />
      <ManagerNavBar/>
    </>
  )
}

export default ManagerLayout
