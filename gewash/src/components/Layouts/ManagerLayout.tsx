import ManagerNavBar from "../../components/pages/Manager/ManagerNavBar"
import { Outlet } from "react-router-dom"

function ManagerLayout() {
  return (
    <>
      <Outlet />
      <ManagerNavBar/>
    </>
  )
}

export default ManagerLayout
